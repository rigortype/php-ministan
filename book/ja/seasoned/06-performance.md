# The Seasoned ministan — S6: パフォーマンス（結果キャッシュ）

> ＊この章のコードはスナップショット [`impls/seasoned/06-performance`](../../../impls/seasoned/06-performance) にあります（この章の到達点は `git tag seasoned-06`）。

応用編の最終章。数千ファイルのコードベースで、変更のたびに全ファイルを解析するのは
非現実的です。CI でも、ローカルでも、待たされるツールは使われません。最後の一歩は
**結果キャッシュ**です。

## キャッシュの原理

単純な事実に乗ります —— **ファイルの内容が変わっていなければ、結果も変わらない**。
だから「内容のハッシュ」をキーに結果を保存し、次回はそれを返します
（[`ResultCache`](../../../impls/seasoned/06-performance/src/Cache/ResultCache.php)）:

```php
private function pathFor(string $code): string
{
    return $this->directory . '/' . sha1($this->salt . "\0" . $code) . '.json';
}
```

鍵は **salt** です。解析器のロジックやレベルが変われば結果も変わるので、salt に
バージョンとレベルを混ぜます。これにより、レベルを上げたり ministan を更新したりすると
**キャッシュが自動的に無効**になります。ファイルパスは鍵に**含めません**——同じ内容なら、
どこにあっても結果は同じだからです。

## 解析器につなぐ

`Analyser` は、解析前にキャッシュを引き、ヒットすればそれを返します
（[`Analyser`](../../../impls/seasoned/06-performance/src/Analyser/Analyser.php)）:

```php
if ($this->cache !== null) {
    $cached = $this->cache->load($code);
    if ($cached !== null) {
        // キャッシュは (メッセージ, 行) だけ持つ。ファイル名は今のものを付け直す。
        return array_map(fn ($e) => new Error($e['message'], $file, $e['line']), $cached);
    }
}
$errors = $this->computeErrors($code, $file);
$this->cache?->save($code, /* 単純化した errors */);
```

キャッシュにはメッセージと行だけを保存し、ファイル名は読み出し時に付け直します。
同じ内容のファイルがパスを変えても正しく扱えます。

## 動かす

```console
$ dev/bin/ministan analyse --cache src   # 1 回目: 計算してキャッシュ
$ dev/bin/ministan analyse --cache src   # 2 回目: 変わらないファイルはキャッシュから
```

変更したファイルだけが再解析され、残りは一瞬で返ります。

> 簡略化: 並列実行（プロセス分割）と、ファイル間依存によるキャッシュ無効化は見送り。
> ministan はファイルを独立に解析する（外部シンボルはネイティブ参照）ので、内容ハッシュ
> だけで十分整合します。クラス定義の変更が利用側へ波及する無効化は、本格的な
> DependencyTracker の領域です。

## 応用編、完 —— そして本物の PHPStan へ

`Hello, World.` の 1 行から、ここまで来ました。基礎編で芯（パース→スコープ→推論→
絞り込み→ルール→報告）を通し、応用編で実用の肉付けをしました:

- 設定（NEON）・拡張・ignoreErrors
- constant array shape・配列の型
- ジェネリクス（`@template` と substitution）
- 制御フロー絞り込み（早期 return・assert・match）
- 参照渡しの出力引数・スタブ
- 結果キャッシュ

ministan は自分自身を解析して通る、小さくとも本物の静的解析器です。ここから先——
本物の [PHPStan](https://github.com/phpstan/phpstan-src) のソースを読むと、ひとつひとつの
クラスが「ああ、ministan のあれの本気版だ」と読めるはずです。`Scope`、`Type`、
`TypeSpecifier`、`NodeScopeResolver`、`RuleLevelHelper`——名前も役割も、地続きです。

良い旅を。

## まとめ

- 内容ハッシュをキーに結果をキャッシュし、変わらないファイルの再解析を省く
- salt にバージョンとレベルを混ぜ、ロジック変更で自動無効化する
- 並列・依存無効化は本格実装へ——けれど芯はすべて通した
