# The Seasoned ministan — S7: 推論と検査の精度向上

> ＊この章のコードはスナップショット [`impls/seasoned/07-precision`](../../../impls/seasoned/07-precision) にあります（この章の到達点は `git tag seasoned-07`）。

> 参考書（任意）：union の部分型吸収は TAPL 15 章の部分型（**束** での join）、ループの型ワイドニングは **不動点近似**（データフロー解析・抽象解釈の発想）。型理論の教科書の*先*にある、精度の詰めです。

ここまでで芯は通りました。本章は仕上げ —— これまで「簡略化」として見送ってきた精度を
詰めます。`match` 式の結果型、union の部分型吸収、ループの型ワイドニング、名前付き
引数の型照合。

## union は部分型を吸収する

`int|0` は `int` であるべきです。`0` は `int` の部分型だからです（`int->isSuperTypeOf(42)` は
「int は 42 を含む」＝Yes —— 広い方が上位型）。TypeScript なら union は型チェッカーが勝手に
畳んでくれますが、ministan の `mergeWith` はこれまで**重複を除くだけ**で部分型は畳まなかったため、
`int|0` のような冗長な union が残っていました。そこで `TypeCombinator::union()` に**部分型の吸収**を
入れます（[`TypeCombinator`](../../../impls/seasoned/07-precision/src/Type/TypeCombinator.php)）:

```php
// 既存メンバが $type の上位型なら、$type は不要（int があれば 0 は要らない）。
foreach ($result as $existing) {
    if ($existing->isSuperTypeOf($type)->yes()) {
        continue 2;
    }
}
// 逆に $type が上位型なら、その部分型の既存メンバを取り除く。
$result = array_values(array_filter($result, fn ($e) => !$type->isSuperTypeOf($e)->yes()));
$result[] = $type;
```

これで合流の結果が膨らまず、`match` の結果型などがすっきりします。

## `match` 式の結果型

S4 では `match` の**腕での絞り込み**はできましたが、`match` **式全体の型**は `mixed` の
ままでした。各腕の本体の型を union すれば求まります —— ただし各腕は、その条件で
絞り込んだスコープで評価します（[`Scope::matchType`](../../../impls/seasoned/07-precision/src/Analyser/Scope.php)）:

```php
$armScope = $matchesTrue
    ? $specifier->specify($cond, $remaining)->truthy
    : $specifier->specifyEquality($expr->cond, $cond, $remaining)->truthy;
$armTypes[] = $armScope->getType($arm->body); // 絞り込まれたスコープで本体の型を求める
// …最後に union…
```

`$shape instanceof Circle => $shape->radius()` の腕は、`$shape` が `Circle` に
絞り込まれた世界で `radius(): int` を解決し `int` に。`default => 0` は `0`。union して
`int`。これで `area(): int` が最も厳しい level 9 でも通ります。

## ループの型ワイドニング（不動点近似）

ループ本体は一度きりではなく、何周も回ります。`$x` がループ内で書き換わるなら、2 周目の
`$x` は「前周の代入」を反映した型のはず。これを **2 パス**で近似します
（[`analyseLoopBody`](../../../impls/seasoned/07-precision/src/Analyser/NodeScopeResolver.php)）:

```php
// 1. 無音（ルール非発火）で本体を辿り、ループをまたぐ代入で型を広げる
$discovered = $this->silently(fn () => $this->processStmts($stmts, $entry));
$widened = $entry->mergeWith($discovered);
// 2. 広げたスコープで本体を 1 度だけ本解析する（ルールはこの 1 回のみ発火）
$result = $this->processStmts($stmts, $widened);
// ループは 0 回かもしれない。広げたスコープと本解析結果を合流して返す
return $widened->mergeWith($result);
```

肝は **無音パス**です。ルールを二度発火させないよう、発見パスではコールバックを
一時的に無効化します。これで重複報告なしに、2 周目の型を踏まえられます:

> なぜ 2 周で止めてよいのか。型は合流のたびに**広がる一方**（単調）で、1 周分の代入を
> 織り込めば多くの場合そこで頭打ちになるからです。収束するまで反復する「真の不動点」は
> 見送り（まとめ参照） —— だから見出しも「不動点**近似**」です。

```php
$prev = 'start';
foreach ($items as $item) {
    $current = $prev;  // 'item'|'start' — 前周で $prev は 'item' になりうる
    $prev = 'item';
}
```

## 名前付き引数の型照合

S5 で名前付き引数は「位置が崩れる」と見送りました。パラメータ**名**をリフレクションに
持たせれば、名前から位置を逆引きできます（[`ArgumentTypeChecker`](../../../impls/seasoned/07-precision/src/Rules/ArgumentTypeChecker.php)）:

```php
$index = $arg->name !== null
    ? array_search($arg->name->toString(), $parameterNames, true) // 名前 → 位置
    : $position;
```

```console
$ dev/bin/ministan analyse examples/seasoned/precision.php
 …
   Parameter #2 of function box() expects int, 'big' given.   # box(size: 'big')
```

## まとめ

- union は**部分型を吸収**して膨張を防ぐ（`int|0` → `int`）
- `match` 式の結果型を、腕ごとに絞り込んだスコープでの本体型の union として求める
- ループは **無音発見パス＋本解析**の 2 パスで、ループをまたぐ型を広げる
- パラメータ名をリフレクションに持たせ、**名前付き引数**も型照合する

> なお残る簡略化: 真の不動点（収束までの反復）、配列要素のフロー、交差型、変性など。

## 二部、完 —— そして PHPStan へ

`Hello, World.` の 1 行から、ここまで来ました。基礎編で芯（パース→スコープ→推論→
絞り込み→ルール→報告）を通し、応用編で実用の肉付けをしました —— 設定（NEON）・拡張、
配列の型、ジェネリクス、制御フロー絞り込み、参照渡しとスタブ、結果キャッシュ、そして
推論と検査の精度向上。

ministan は自分自身を解析して通る、小さくとも一つの静的解析器です。ここから先 ——
[PHPStan のソース](https://github.com/phpstan/phpstan-src)を開くと、ひとつひとつのクラスが
「ああ、ministan のあれを本気で作るとこうなるのか」と読めるはずです。`Scope`、`Type`、
`TypeSpecifier`、`NodeScopeResolver`、`RuleLevelHelper` —— 名前も役割も、地続きです。

良い旅を。
