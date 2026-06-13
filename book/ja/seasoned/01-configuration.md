# The Seasoned ministan — S1: 設定と拡張

> ＊コードはライブ実装ツリー [`dev/`](../../../dev) にあります（この章の到達点は `git tag seasoned-01`）。

基礎編は CLI フラグだけで動かしました。実務では、レベルやパス、無視するエラー、独自
ルールを **設定ファイル** にまとめたい。PHPStan に倣い **NEON** を採用します。

## なぜ NEON か

`phpstan.neon` と同じ書き味にすることで、PHPStan ユーザーがそのまま読めます。`nette/neon`
を入れ、[`ConfigurationLoader`](../../../dev/src/Configuration/ConfigurationLoader.php) で
[`Configuration`](../../../dev/src/Configuration/Configuration.php) に写します:

```neon
parameters:
    level: 6
    paths:
        - src
    ignoreErrors:
        - '#Call to an undefined method#'
rules:
    - App\Rules\MyRule
```

```php
$data = Neon::decode((string) file_get_contents($file));
$parameters = (array) ($data['parameters'] ?? []);
return new Configuration(
    (int) ($parameters['level'] ?? RuleRegistryFactory::DEFAULT_LEVEL),
    $this->stringList($parameters['paths'] ?? []),
    $this->stringList($parameters['ignoreErrors'] ?? []),
    $this->stringList($data['rules'] ?? []),
);
```

## CLI が設定を上書きする

`AnalyseCommand` は既定で `ministan.neon` を読み、CLI 引数で上書きします
（`--level` や明示パスが NEON より優先）。この「設定の層」をきれいに重ねるのが、
使いやすい解析ツールの条件です:

```php
$config = $this->loadConfiguration($configFile);
$level  = $cliLevel ?? $config->level;
$paths  = $cliPaths !== [] ? $cliPaths : $config->paths;
```

## ignoreErrors —— パターンで黙らせる

baseline が「この具体的な箇所」を無視するのに対し、`ignoreErrors` は「この**種類**の
メッセージ」を正規表現で無視します
（[`IgnoredErrorHelper`](../../../dev/src/Configuration/IgnoredErrorHelper.php)）:

```php
foreach ($this->patterns as $pattern) {
    if (@preg_match($pattern, $message) === 1) {
        return true;
    }
}
```

```console
$ dev/bin/ministan analyse --configuration=examples/seasoned/ministan.neon
[OK] No errors   # undefined method の指摘を ignoreErrors が握りつぶした
```

## 拡張点 —— カスタムルール

設定の `rules:` に挙げたクラスを登録できます。`RuleRegistryFactory::createForLevel()` が
組み込みルールに**追加ルール**を継ぎ足します:

```php
public function createForLevel(int $level, array $extraRules = []): RuleRegistry
{
    // …組み込みルールをレベルで選別…
    foreach ($extraRules as $rule) {
        $active[] = $rule;
    }
    return new RuleRegistry($active);
}
```

`AnalyseCommand` はクラス名から実体を作り（オートロード可能で `Rule` を実装していれば）、
登録します。これで利用者は、自分のプロジェクト規約を表すルールを足せます——PHPStan の
エコシステムが豊かなのは、この拡張点があるからです。

## まとめ

- 設定は NEON（`nette/neon`）で読み、`Configuration` に集約する
- CLI が NEON を上書きする層構造にする
- `ignoreErrors` はパターンで、baseline は箇所で指摘を黙らせる
- `rules:` でカスタムルールを登録できる拡張点を開いた

次の S2 では **配列の型** を深めます。`array{...}` の constant array shape、配列アクセスの
戻り値型、`foreach` の要素型推論へ。
