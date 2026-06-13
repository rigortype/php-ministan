# Part 9 — ツール化

> ＊この章のコードはスナップショット [`impls/09-tooling`](../../impls/09-tooling) にあります（この章の到達点は `git tag part-09`）。

基礎編の最終章。型チェッカーの核はできました。あとは**実用ツール**にする最後の一歩——
ディレクトリ再帰、複数フォーマット、baseline です。

## ディレクトリを丸ごと

これまで 1 ファイルずつでしたが、実務ではディレクトリを渡します
（[`FileFinder`](../../impls/09-tooling/src/Analyser/FileFinder.php)）:

```php
foreach ($iterator as $info) {
    if ($info->isFile() && $info->getExtension() === 'php') {
        $files[] = $info->getPathname();
    }
}
```

`Analyser::analyse(array $files)` が各ファイルを順に解析し、エラーをまとめます。

```console
$ dev/bin/ministan analyse dev/src
[OK] No errors
```

自分自身のソースツリーを丸ごと、1 コマンドで通せるようになりました。

## 出力を差し替える —— `ErrorFormatter`

人間向けの表と、CI 向けの JSON。出力を**インターフェイス**で抽象化します
（[`ErrorFormatter`](../../impls/09-tooling/src/Output/ErrorFormatter.php)）。
[`TableErrorFormatter`](../../impls/09-tooling/src/Output/TableErrorFormatter.php) はファイルごとにまとめ、
[`JsonErrorFormatter`](../../impls/09-tooling/src/Output/JsonErrorFormatter.php) は機械可読に出します:

```console
$ dev/bin/ministan analyse --error-format=json examples/reflection.php
{
    "totals": { "file_errors": 1 },
    "files": {
        "examples/reflection.php": {
            "errors": 1,
            "messages": [ { "message": "Call to an undefined method …", "line": 17 } ]
        }
    }
}
```

## baseline —— レガシーに導入する第一歩

既存の巨大なコードベースに型チェッカーを入れると、何千もの指摘が出ます。全部直すまで
CI を赤にはできない。そこで **baseline** —— 今ある指摘を「許容済み」として固め、
**新しく入った指摘だけ**を赤にする運用にします（[`Baseline`](../../impls/09-tooling/src/Output/Baseline.php)）。
「これ以上増やさない」守りであり、**変更の恐怖で止まっていた改善を再開させる**攻めでもあります:

```console
$ dev/bin/ministan analyse --generate-baseline=ministan-baseline.json src
Baseline written to ministan-baseline.json (1234 errors).

$ dev/bin/ministan analyse --baseline=ministan-baseline.json src
[OK] No errors
```

突き合わせは (ファイル, メッセージ) の組で行う簡略版です（PHPStan は件数まで見ます）。
これで新しく入った指摘だけが赤くなります。

> baseline はこの本がモデルにする PHPStan の専売ではありません。PHP では Psalm が先行し、
> 既存エラーを「grandfather（既存分を据え置く）」発想を広めました。「恐怖を取り除いて変更を
> 再開する」というこの考えは、PHP 静的解析の共有財産です。

## 終了コード

CI のために、指摘があれば `1`、無ければ `0`。baseline 生成時は `0`。`--error-format=json`
と組み合わせれば、エディタや CI から素直に扱えます。

## 基礎編、完

`Hello, World.` の 1 行から始めて、ここまで来ました:

- **パース → スコープ伝播 → 型推論 → 絞り込み → ルール適用 → 報告**
- 不変 `Scope`、`Type` の代数と三値論理、`UnionType`、リフレクション、PHPDoc
- レベル制と baseline を備えた実用ツール
- そして全行程を通じて **non-rejecting** —— 分からないことには黙る

そして、Part 0 の冒頭で「最終的にこうなります」と掲げた、あの画面を思い出してください:

```console
$ ministan analyse src/
 src/Foo.php:42
   Parameter #1 $name of function greet() expects string, int given.

 [ERROR] Found 1 error
```

ministan はいま、これを本当に出します。引数の型不一致を、Part 4〜7 で積み上げた型推論と
Part 8 のルールが導いている —— 最初に掲げたゴールに、自分の手で辿り着きました。

ministan は自分自身を解析して通ります。小さくとも、本物の静的解析器です。

## 積み残しと、その先（The Seasoned ministan）

基礎編は「最小で芯を通す」ことを優先し、多くを後回しにしました:

- 本格ジェネリクス／テンプレート型（`@template T`）→ S3
- constant array shape・配列アクセスの型 → S2、ループの型ワイドニング → S7
- by-ref 出力引数（`preg_match($s, $m)` の `$m`）→ S5、名前付き引数 → S7
- スタブによる外部シグネチャ → S5（PHPDoc のクラス名の名前空間解決は応用編のさらに先へ）
- 設定ファイル → S1、結果キャッシュ → S6（並列実行は本格実装の領域）

応用編 **The Seasoned ministan** で、これらに踏み込みます。

## まとめ

- ディレクトリ再帰・複数フォーマット・baseline で実用ツールに仕上げた
- 出力はインターフェイスで差し替え可能
- baseline はレガシー導入の第一歩
- 基礎編 10 章で、PHPStan のエッセンスを一周した
