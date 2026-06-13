# ministan —— PHP 静的解析器を一から作る

> Writing a PHP static analyzer: Step by Step, from one syntax error.

[PHPStan](https://github.com/phpstan/phpstan) のエッセンスを最小核に蒸留し、
[nikic/php-parser](https://github.com/nikic/PHP-Parser) を基盤に、PHP の静的解析・型推論・
型チェッカーを **一行の構文エラー報告から少しずつ育てて作る** チュートリアルです。

`Hello, World.` の 1 行を解析するところから始め、章ごとに変数を追い、型を推論し、絞り込み、
ジェネリクスを解決し、最後は **自分自身を解析して通る**（dogfooding）小さな静的解析器に
仕上げます。読み終える頃には、本物の PHPStan のソースが「ああ、ministan のあれの本気版だ」と
読めるようになっているはずです。

## この本について

「静的解析器を *使う*」から「静的解析器を *作る*」へ —— それがこの本のねらいです。

**想定読者**

- 型を書く PHP 実務者（`int $x`・`: string`・typed property・enum・`readonly` を日常的に書く人）。
- PHPStan や Psalm を「なんとなく使っている」けれど、**中身がどう動いているか**を知りたい人。
- 静的解析の存在は知らないが、型注釈を書いていて「これは誰がどう検査しているのか」が気になる人。

**前提にしないもの**

- 型理論の知識は要りません。必要なものは、必要になった章で**作りながら**学びます。
- 数式の厳密さも求めません（やさしい基礎編／形式は応用編で少しだけ）。

> 言葉に迷ったら [用語集](glossary.md)（各語に初出の章つき）へ。

## 環境

- **PHP 8.3 以上**（本文は 8.3 の機能を素直に使います）と **Composer**。
- 基盤は `nikic/php-parser`。PHPDoc を読む章から `phpstan/phpdoc-parser`、設定の章から
  `nette/neon` を足します。各章のコードは `composer install` だけで動きます。

## 設計哲学 —— non-rejecting

ひとつだけ、最初に約束しておく軸があります:

> **構文エラーでないコードは受理する。** 分からない型は `mixed` に縮退させ、確信が持てない
> ことについては黙る。

「分からないから一応エラー」を積み重ねた解析器は誰にも使われません。**偽陽性を出さないこと**を
最優先に置く —— この約束が、レベル段階制や `mixed` の意味論として全章に効いてきます。
詳しくは [Part 0](00-overview.md)。

## 二部構成の地図

### The Little ministan（基礎編） —— 芯を通す

パース → スコープ → 型推論 → 絞り込み → ルール → 報告 の一本道を、最小核で通します。

| Part | テーマ | 完成する機能 |
|------|--------|--------------|
| [0](00-overview.md) | 全体像と Hello World | 解析パイプラインを通し、構文エラーを報告 |
| [1](01-php-parser.md) | PHP-Parser と AST | 構文ベースのルール（`Rule` インターフェイス） |
| [2](02-scope.md) | Scope と変数追跡 | 未定義変数の使用検出 |
| [3](03-type-system.md) | 型システムの基礎 | `Type` と三値論理・定数型 |
| [4](04-type-inference.md) | 型推論 | `annotate` で推論型を表示 |
| [5](05-narrowing.md) | Union と絞り込み | `instanceof`／`is_*`／`=== null` で narrowing |
| [6](06-reflection.md) | リフレクション | 戻り値推論・未定義メソッド検出 |
| [7](07-phpdoc.md) | PHPDoc | `@param`／`@return`／`@var` を型に |
| [8](08-rules-and-levels.md) | ルールとレベル | 引数／戻り値の型不一致・level 0–max |
| [9](09-tooling.md) | ツール化 | ディレクトリ再帰・JSON 出力・baseline |

### The Seasoned ministan（応用編） —— 実用の肉付け

基礎編を前提に、現場で効く精度と仕組みを足します（意図的に難度を上げた巻です）。

| S | テーマ | 完成する機能 |
|---|--------|--------------|
| [1](seasoned/01-configuration.md) | 設定と拡張 | NEON 設定・ignoreErrors・カスタムルール |
| [2](seasoned/02-arrays.md) | 配列を深める | constant array shape・配列アクセス・`foreach` 要素型 |
| [3](seasoned/03-generics.md) | ジェネリクス | `@template`・型引数・型変数の置換 |
| [4](seasoned/04-control-flow.md) | 制御フローと高度な narrowing | 早期 return・`assert`・`match` の腕 |
| [5](seasoned/05-byref-stubs.md) | 参照渡しとスタブ | by-ref 出力引数・スタブによる補完 |
| [6](seasoned/06-performance.md) | パフォーマンス | 結果キャッシュ |
| [7](seasoned/07-precision.md) | 推論と検査の精度向上 | match 式結果型・union 吸収・ループ型ワイドニング・名前付き引数 |

## 読み方 —— `dev/` と `impls/`

開発は単一のライブツリー [`dev/`](../../dev) で行い、章境界を git タグ（`part-00`…`part-09`,
`seasoned-01`…）で刻んでいます。本文がリンクするコードは、**各章の完成スナップショット**
`impls/NN-*`（その章まで書いたときのコード）です。`dev/` は全章を育てきった最終形なので、
先の章のコードまで見たいときはそちらを。

任意の章は、それだけで動かせます:

```console
$ cd impls/02-scope && composer install
$ ./bin/ministan analyse examples/with-var-dump.php
 examples/with-var-dump.php:12
   Called var_dump().

 [ERROR] Found 1 error
```

スナップショットは `tools/build-impls.sh` が git タグから機械生成しています（手で編集しません）。
詳しくはリポジトリの [WORKFLOW.md](../../WORKFLOW.md)。

## その先 —— 本物の PHPStan へ

ministan は最小核です。`Scope`・`Type`・`TypeSpecifier`・`NodeScopeResolver`・`RuleLevelHelper`
…という名前も役割も、本物の [PHPStan のソース](https://github.com/phpstan/phpstan-src)と
地続きです。読み終えたら、ぜひ本物を開いてみてください —— きっと、もう怖くありません。

では、[Part 0](00-overview.md) から。良い旅を。
