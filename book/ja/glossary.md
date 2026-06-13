# 用語集

本書で繰り返し出る言葉を、**初出の章**つきでまとめます。詰まったら戻ってきてください。
ならびは初出順（おおむね作る順）です。

| 用語 | ひとことで | 初出 |
|------|-----------|------|
| **non-rejecting** | 構文エラーでないコードは受理し、分からないことには黙る（`mixed` に縮退）。偽陽性を出さないことを最優先にする本書の軸。 | [Part 0](00-overview.md) |
| **AST（抽象構文木）** | ソースコードを「文字列」ではなく木構造として表したもの。`nikic/php-parser` が作る。解析はこの木に対して行う。 | [Part 1](01-php-parser.md) |
| **`Rule`（ルール）** | 1 種類の AST ノードを検査し、問題があれば報告する検査器。`getNodeType()`（どのノードか）と `processNode()`（何を報告するか）の 2 つ。 | [Part 1](01-php-parser.md) |
| **`Scope`（スコープ）** | ある地点で「いま分かっていること」を表す不変オブジェクト。最初は定義済み変数の集合、のちに変数→型の環境になる。 | [Part 2](02-scope.md) |
| **`NodeScopeResolver`** | AST を下りながら `Scope` を運び、各地点でルール（やコールバック）を呼ぶ再帰下降。読み取り文脈と書き込み文脈を区別するのが肝。 | [Part 2](02-scope.md) |
| **`Type`（型）** | 「値の集合」を表す代数的オブジェクト。`describe()`／`isSuperTypeOf()`／`accepts()` の 3 つで型どうしの関係を問う。 | [Part 3](03-type-system.md) |
| **`TrinaryLogic`（三値論理）** | Yes／Maybe／No の三値。`mixed` は int「かもしれない」など、「たぶん」を一級市民にする。レベル制の軸になる。 | [Part 3](03-type-system.md) |
| **部分型（`isSuperTypeOf`）** | 型を集合とみたときの包含関係。`int ⊇ 42` は Yes、逆の `42 ⊇ int` は Maybe（非対称）。 | [Part 3](03-type-system.md) |
| **`mixed` / `never`** | `mixed`＝最上位（すべての値・「分からない」の縮退先）。`never`＝最下位（空集合）。型の束の両端。 | [Part 3](03-type-system.md) |
| **定数型（constant type）** | `42`・`'foo'`・`true` のような単一の値の型。`$x = 42` の直後の型は `int` ではなく `42`。推論の切れ味の源。 | [Part 3](03-type-system.md) |
| **型推論（`Scope::getType`）** | 式の構造から型をボトムアップで組み立てる営み。`annotate` で覗ける。大域推論ではなく、宣言と式からの伝播＋局所絞り込み。 | [Part 4](04-type-inference.md) |
| **`UnionType`（union 型）** | 「いずれかの型」`int\|string`。絞り込みの合流で生まれる。生成・正規化は `TypeCombinator` が担う。 | [Part 5](05-narrowing.md) |
| **絞り込み（narrowing）／`TypeSpecifier`** | 条件が分岐ごとに型を狭めること。`instanceof`／`is_*`／`=== null`／`isset` から、真・偽それぞれの `Scope` を導く。 | [Part 5](05-narrowing.md) |
| **リフレクション（`ReflectionProvider`）** | クラス・メソッド・関数のシグネチャを引く窓口。対象コードの宣言＋ネイティブの二段構え。`ObjectType` の継承判定や戻り値推論に使う。 | [Part 6](06-reflection.md) |
| **PHPDoc** | `@param`／`@return`／`@var`／`@template` などのコメント注釈。ネイティブ宣言より精密な型を書ける（実行時には消える＝解析器が意味を与える）。 | [Part 7](07-phpdoc.md) |
| **`RuleLevelHelper`／レベル** | 三値の `Maybe` をレベルに応じて咎めるか素通りさせるか決める仕組み。レベルを上げるほどルールが増え、`mixed` にも厳しくなる（二段構え）。 | [Part 8](08-rules-and-levels.md) |
| **baseline** | 今ある指摘を「許容済み」として固め、新しく入った指摘だけを赤にする運用。レガシー導入で「変更の恐怖」を取り除く（Psalm が先行）。 | [Part 9](09-tooling.md) |
| **dogfood（自己解析）** | 解析器を自分自身のソースに当てること。本書では偽陽性ゼロで通ることを品質の柱にし、見つかった穴が次章の宿題になる。 | [Part 9](09-tooling.md) |
| **ignoreErrors** | メッセージの正規表現にマッチした指摘を無視する設定。「箇所」を黙らせる baseline に対し、「種類」を黙らせる。 | [S1](seasoned/01-configuration.md) |
| **constant array shape（`array{…}`）** | キーごとに値の型が分かる配列。`['id' => 42]` の型は `array<string, int>` ではなく `array{id: 42}`。 | [S2](seasoned/02-arrays.md) |
| **ジェネリクス（`@template`／`TemplateType`）** | 「まだ決まっていない型」`T` を PHPDoc で表す擬似ジェネリクス（Hack 源流・Psalm 先駆け）。実行時には無く、解析器の層にだけ存在する。 | [S3](seasoned/03-generics.md) |
| **置換（substitution）** | 型変数 `T` を具体型に置き換えること。`identity(42)` で `T → 42`、`Box<int>::get(): T` で `T → int`。一方向の代入（双方向の単一化はしない）。 | [S3](seasoned/03-generics.md) |
| **スタブ（stub）** | ネイティブのリフレクションでは表せない型を、PHPDoc 付きの宣言を**パースして**外から補うファイル（Psalm の `.phpstub` 系）。 | [S5](seasoned/05-byref-stubs.md) |
| **by-ref 出力引数** | `preg_match($s, $m)` の `$m` のような参照渡しの引数。関数が書き込むので、読み取りではなく**定義**として扱う（C# の `out`/`ref` 相当）。 | [S5](seasoned/05-byref-stubs.md) |
| **制御フロー絞り込み** | コードの「形」が型を狭めること。早期 return で終わる枝は合流に寄与しない、`assert(...)` は以降を狭める、`match` の腕は条件で絞り込まれる。 | [S4](seasoned/04-control-flow.md) |
| **結果キャッシュ** | ファイルの内容ハッシュをキーに解析結果を保存し、変わらないファイルの再解析を省く仕組み。salt（版・レベル）でロジック変更時に自動無効化。 | [S6](seasoned/06-performance.md) |
| **型ワイドニング（loop widening）** | ループ本体を 2 パス（無音発見＋本解析）で解析し、ループをまたぐ変数の型を広げる不動点近似。型は単調に広がるので近似で足りる。 | [S7](seasoned/07-precision.md) |
| **名前付き引数** | `f(size: 'big')` のように名前で渡す引数。型照合では、パラメータ名から宣言上の位置を逆引きして突き合わせる。 | [S7](seasoned/07-precision.md) |
