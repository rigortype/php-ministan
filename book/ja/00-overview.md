# Part 0 — 全体像と Hello World

> ＊この章のコードは [`impls/00-hello`](../../impls/00-hello) にあります（ライブツリー `dev/` を `git tag part-00` 時点で切り出したスナップショット）。

## このチュートリアルが作るもの

私たちは [PHPStan](https://github.com/phpstan/phpstan) のエッセンスを蒸留した、
小さな静的解析器 **ministan** を一から作ります。最終的にこうなります:

```console
$ ministan analyse src/
 src/Foo.php:42
   Parameter #1 $name of function greet() expects string, int given.

 [ERROR] Found 1 error
```

いきなり全部は作りません。chibivue が「`Hello, World.` の 1 行から Vue.js を書く」ように、
私たちも **1 件の構文エラーを報告する** ところから始め、章ごとに

- 変数を追跡し（Part 2）
- 型を持ち（Part 3）
- 型を推論し（Part 4）
- 型を絞り込み（Part 5）
- クラスを反映し（Part 6）
- PHPDoc を読み（Part 7）
- レベル付きルールを束ね（Part 8）
- 実用ツールに仕上げる（Part 9）

と、動くものを少しずつ育てていきます。

## 静的解析器の解剖図

PHPStan は巨大ですが、骨格は驚くほど素直です。コードを **実行せずに** AST を歩き、
各地点での変数の **型** を推論し、ルールに照らして矛盾を **報告** する。これだけです。

PHP は型宣言を**実行時**に検査します（`int $x` に文字列を渡せば、その行で `TypeError`）。
静的解析器がやるのは、それを**実行する前**に——コードを動かさず、ありえる値の型を机上で
たどって——見つけることです。テストが踏まない経路でも、`$user->nmae` のタイポや型の
取り違えを*出荷前*に捕まえる。これが「実行せずに」の値打ちです。

```
ソースコード
   │  nikic/php-parser
   ▼
  AST  ──▶  NodeScopeResolver ──▶ Scope（各地点の変数→型）
                                     │
                                     ▼
                                  Rule 群 ──▶ Error 群 ──▶ 整形・出力
```

ministan もこの構造を最初から踏襲します。本章ではまだ Scope も Rule もありませんが、
両端 —— **入力（パース）** と **出力（Error の整形）** —— を先に通しておきます。
こうすると、以降の章は「真ん中に機能を足す」作業に集中できます。

## 設計哲学: non-rejecting

ひとつだけ、最初に約束しておくべき哲学があります:

> **構文エラーでないコードは受理する。** 分からない型は `mixed` に縮退させ、
> 確信が持てないことについては黙る。

静的解析器が役に立つかどうかは、**偽陽性をどれだけ出さないか** にかかっています。
「分からないから一応エラー」を積み重ねた解析器は、誰にも使われません。
PHPStan の `mixed` も、レベル段階制（level 0 から少しずつ厳しくする仕組み）も、
すべてこの哲学の表れです。Part 8 でレベルを実装するとき、この約束が効いてきます。

> `mixed` は「何でも入りうる**最上位の型**」であって、TypeScript の `any` のように
> 検査を切り捨てるスイッチではありません。低いレベルでは素通りさせますが、高いレベルでは
> `mixed` の混入も咎めます（Part 8）。「分からない」を黙って通すか咎めるかをレベルで選ぶ
> ―― それが non-rejecting の実装です。

## 作る: パイプラインを通す

Part 0 の `Analyser` は、構文を検証し、**php-parser の構文エラーを ministan の診断
（`Error`）に翻訳する** だけです。

```php
// src/Analyser/Analyser.php（抜粋）
$parser = (new ParserFactory())->createForNewestSupportedVersion();

try {
    $parser->parse($code);
} catch (ParserError $e) {
    return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
}

return []; // 構文が通れば、Part 0 では報告すべき問題はない。
```

報告の単位となる値オブジェクト `Error` は、これから全章で使い続けます:

```php
// src/Analyser/Error.php
final readonly class Error
{
    public function __construct(
        public string $message,
        public string $file,
        public int $line,
    ) {}
}
```

`readonly` プロパティ（PHP 8.1+）で不変にしておくのは、後の章で `Scope` を
**不変オブジェクト** として設計するための地ならしでもあります。
PHPStan の `Scope` が不変なのには理由があり、それは Part 2 で明らかになります。

## 動かす

```console
$ cd dev && composer install && cd ..

$ dev/bin/ministan analyse examples/hello.php
[OK] No errors

$ dev/bin/ministan analyse examples/broken.php
 examples/broken.php:8
   Syntax error, unexpected '}', expecting ';'

 [ERROR] Found 1 error
```

終了コードも CI で使えるよう、問題があれば `1`、なければ `0` を返します。

## まとめ

- 静的解析器の骨格は **パース → スコープ解決 → ルール適用 → 報告**
- 本章では両端（パースと報告）を通し、`Analyser` / `Error` / `ErrorFormatter` という
  これから育てる器を用意した
- 哲学は **non-rejecting**: 分からないことには黙る

次の Part 1 では AST に踏み込み、最初の実質的なルール —— 構文パターンに基づく検査 ——
と `Rule` インターフェイスを導入します。
