# S6: パフォーマンス（結果キャッシュ）

> ＊この章のコードはスナップショット [`impls/looking-glass/06-performance`](../../../impls/looking-glass/06-performance) にあります（この章の到達点は `git tag seasoned-06`）。

ここまで型の精度を積み上げてきました。本章はひと息 —— 型の話からいったん離れ、**実用ツール
の体感速度**を支える一点、**結果キャッシュ**だけを扱う短い箸休めの章です。数千ファイルの
コードベースで、変更のたびに全ファイルを解析するのは非現実的です。CI でも、ローカルでも、
待たされるツールは使われません。仕上げの S7 へ進む前に、ここで速度の土台を据えておきます。

## キャッシュの原理

単純な事実に乗ります —— **ファイルの内容が変わっていなければ、結果も変わらない**。
だから「内容のハッシュ」をキーに結果を保存し、次回はそれを返します
（[`ResultCache`](../../../impls/looking-glass/06-performance/src/Cache/ResultCache.php)）:

```php
private function pathFor(string $code): string
{
    return $this->directory . '/' . sha1($this->salt . "\0" . $code) . '.json';
}
```

鍵は **salt** です。解析器のロジックやレベルが変われば結果も変わるので、salt に
バージョンとレベルを混ぜます。ここでの「バージョン」は ministan が持つ**スキーマ版の定数
文字列**で、解析ロジックを変えたら手で上げます。これにより、レベルを上げたり ministan を
更新したりすると **キャッシュが自動的に無効**になります。ファイルパスは鍵に**含めません**
—— 同じ内容なら、どこにあっても結果は同じだからです。

> 裏返すと、カスタムルールを足しただけではこの版が上がらず、キャッシュが古いままになりえます
> —— 実 PHPStan は PHPStan 本体のバージョンと設定（NEON）のハッシュまで salt に織り込んで、
> ここを取りこぼさないようにしています。

## 解析器につなぐ

`Analyser` は、解析前にキャッシュを引き、ヒットすればそれを返します
（[`Analyser`](../../../impls/looking-glass/06-performance/src/Analyser/Analyser.php)）:

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

## まとめ

- 内容ハッシュをキーに結果をキャッシュし、変わらないファイルの再解析を省く
- salt にバージョンとレベルを混ぜ、ロジック変更で自動無効化する
- 並列実行・ファイル間依存による無効化は本格実装（DependencyTracker）の領域

次の S7（応用編の最終章）では、推論と検査の**精度**を詰めます —— `match` 式の結果型、
union の部分型吸収、ループの型ワイドニング、名前付き引数。
