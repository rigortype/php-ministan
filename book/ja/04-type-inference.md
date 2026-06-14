# Part 4 — 型推論と `annotate`

> ＊この章のコードはスナップショット [`impls/04-type-inference`](../../impls/04-type-inference) にあります（この章の到達点は `git tag part-04`）。

> 参考書（任意）：『しくみ』2 章「真偽値の型と数値の型」／TAPL 8 章「型付き算術式」。式に型をつける規則を、リテラルと演算からコードの形で組みます。

Part 3 で型の語彙を作りました。Part 2 で変数を追う器を作りました。本章でこの二つを
繋ぎます。`Scope` を「変数が定義済みか」から **「変数が何の型か」** へ育て、任意の式の型を
**推論** する `Scope::getType()` を実装します。そして推論結果を目で見る
`ministan annotate` を追加します。型システムが初めて「動いて見える」章です。

## `Scope` が型を持つ

これまで `array<string, true>`（定義済み集合）だった中身を、`array<string, Type>`
（変数→型）に変えます。これだけで `Scope` は型環境になります。

```php
// src/Analyser/Scope.php
public function getVariableType(string $name): Type
{
    return $this->variableTypes[$name] ?? new MixedType(); // 未定義は mixed に縮退
}
```

未定義変数を引いても例外を投げず `mixed` を返すのが non-rejecting の流儀。Part 2 の
未定義検出（`hasVariable`）はそのまま残り、型の問い合わせは常に答えを返します。

## `getType()` —— 推論の本体

`Scope::getType(Expr): Type` が PHPStan の中核 `Scope::getType()` に当たります。
式の構造から型を組み立てます:

> ここでの「推論」は、リテラル・宣言型・式の構造から型を**ボトムアップで組み立てる**
> 営みです。ML 系のような、関数全体の型を未知数として**解く**大域推論ではありません。
> 分からないものは解かず `mixed` に縮退させる —— それが non-rejecting の流儀です。

```php
public function getType(Expr $expr): Type
{
    return match (true) {
        $expr instanceof Scalar\Int_    => new ConstantIntegerType($expr->value), // 42 → 42
        $expr instanceof Scalar\String_ => new ConstantStringType($expr->value),  // 'x' → 'x'

        $expr instanceof Expr\Variable  => is_string($expr->name)
            ? $this->getVariableType($expr->name)
            : new MixedType(),

        $expr instanceof Expr\BinaryOp\Concat => new StringType(),            // . は常に string
        $expr instanceof Expr\BinaryOp\Plus,
        $expr instanceof Expr\BinaryOp\Minus,
        $expr instanceof Expr\BinaryOp\Mul    => $this->arithmeticType($expr), // 算術

        $expr instanceof Expr\BinaryOp\Identical,
        /* …比較・論理… */
        $expr instanceof Expr\BooleanNot      => new BooleanType(),           // 比較は常に bool

        default => new MixedType(), // 分からないものは mixed
    };
}
```

リテラルが**定数型**になるのが効いています。`42` は `int` ではなく `42`。だから
`annotate` で `$a : 42` と表示され、`match` の網羅性判定（後章）の土台になります。

算術は Part 3 の型束を使って組み立てます。両辺が int なら int、両辺が numeric で
どちらかが float なら float、それ以外は mixed:

```php
private function arithmeticType(Expr\BinaryOp $expr): Type
{
    $int = new IntegerType();
    if ($int->isSuperTypeOf($this->getType($expr->left))->yes()
        && $int->isSuperTypeOf($this->getType($expr->right))->yes()
    ) {
        return new IntegerType();
    }
    // …どちらかが float なら float、それ以外は mixed…
}
```

> 定数畳み込み（`42 + 1` を `43` と推論する）は ministan ではしません。`$a + 1` の型は
> `int` に留めます —— 解析を重くする割に効用が限られるからです（実 PHPStan は `43` まで
> 畳み込みます）。

## 代入で型を結ぶ

`NodeScopeResolver` は代入のたびに右辺を推論し、変数へ結びつけるようになりました:

```php
private function processAssign(Expr\Assign $node, Scope $scope): Scope
{
    $scope = $this->processNode($node->expr, $scope);  // 右辺を解析
    $type  = $scope->getType($node->expr);             // 右辺の型を推論
    return $this->processAssignTarget($node->var, $type, $scope); // 変数に結ぶ
}
```

パラメータも宣言から型を得ます（`int $n` → `IntegerType`）。クラス型・nullable・union は
リフレクションと PHPDoc を扱う Part 6〜7 まで `mixed` に縮退させます。

## 走査を一般化する —— node callback

`annotate` を作るにあたり、`NodeScopeResolver` を作り変えました。これまでルール適用を
直接抱えていたのを、**「各ノードで (node, scope) を渡して呼ぶコールバック」** に
一般化したのです。PHPStan の `NodeScopeResolver` もこの形（nodeCallback）です。

```php
public function __construct(callable $nodeCallback) { /* … */ }
```

- `analyse` はルールを走らせるコールバックを渡す（[`Analyser`](../../impls/04-type-inference/src/Analyser/Analyser.php)）
- `annotate` は型を集めるコールバックを渡す（[`AnnotateCommand`](../../impls/04-type-inference/src/Command/AnnotateCommand.php)）

同じスコープ伝播を二つの用途で共有できます。コールバックは各ノードの**処理前**に
呼ばれるので、代入ノードではまだ束縛前のスコープが渡り、右辺を素直に推論できます:

```php
new NodeScopeResolver(function (Node $node, Scope $scope) use (&$rows): void {
    if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable) {
        $rows[] = [$node->getStartLine(), '$' . $node->var->name,
                   $scope->getType($node->expr)->describe()];
    }
});
```

## 動かす

```console
$ dev/bin/ministan annotate examples/types.php
examples/types.php
     5  $a     : 42
     6  $b     : int
     7  $c     : 'hello'
     8  $d     : string
     9  $e     : bool
    10  $f     : int
    14  $text  : string
    16  return : string
```

`$a` は定数型 `42`、`$b = $a + 1` は畳み込まず `int`、`$d` は連結で `string`、
`$e` は比較で `bool`。関数内では `int` パラメータ `$n` から `'n=' . $n` が `string` と
推論され、`return` まで型が流れています。

## まとめ

- `Scope` を **変数→型** の環境へ育て、`getType()` で式の型を推論する
- リテラルは**定数型**になり、演算は Part 3 の型束で組み立てる（定数畳み込みはしない）
- 代入で右辺の型を変数へ結ぶ。未知は `mixed` に縮退（non-rejecting）
- `NodeScopeResolver` を **node callback** に一般化し、`analyse` と `annotate` で共有
- `ministan annotate` で推論を可視化できる

次の Part 5 では、型を **絞り込み**ます。`if ($x instanceof Foo)` や `is_int($x)`、
`$x === null` といった条件が、分岐の中で `$x` の型をどう狭めるか。`UnionType` を導入し、
`Scope::mergeWith()` を「mixed への退避」から正しい合併へと精密化します。
