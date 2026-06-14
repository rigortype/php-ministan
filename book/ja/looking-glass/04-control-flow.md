# S4: 制御フローと高度な narrowing

> ＊この章のコードはスナップショット [`impls/looking-glass/04-control-flow`](../../../impls/looking-glass/04-control-flow) にあります（この章の到達点は `git tag seasoned-04`）。

> 参考書（任意）：**フロー解析**（データフロー解析）の一般論が近い領域。型理論の教科書（TAPL／『しくみ』）に直接の対応章はありません —— 絞り込みを制御フロー全体へ広げるのは実装側の工夫です。

絞り込みは `if` の枝の中だけのものではありません。**早期 return** の後、`assert()` の後、
`match` の腕の中 —— コードの「形」が型を狭めます。本章はその制御フロー感覚を実装します。

> 参考書メモ：Part 5 で入れた絞り込み（occurrence typing）を、`if` の枝だけでなく早期 return・
> `assert`・`match` の腕へと **制御フロー全体**に広げるのが本章です。「どの地点で何が成り立つか」を
> 辿るこの感覚は、型理論の型付け規則よりも **データフロー解析**（プログラム解析）に近い領域です。

## 早期 return —— 終わる枝は合流に寄与しない

これは PHP で最も多いパターンのひとつです:

```php
function plus_one(?int $x): int
{
    if ($x === null) {
        return 0;
    }
    $y = $x + 1; // ここで $x は int のはず（null なら上で return 済み）
    return $y;
}
```

鍵は「**return / throw で終わる枝は、if の後の世界に合流しない**」こと。`processIf` で
枝の終端を判定し、終わる枝を合流から外します（[`NodeScopeResolver`](../../../impls/looking-glass/04-control-flow/src/Analyser/NodeScopeResolver.php)）:

```php
$thenScope = $this->processStmts($node->stmts, $specified->truthy);
if (!$this->alwaysTerminates($node->stmts)) {
    $endScopes[] = $thenScope; // 終わる枝は合流に入れない
}
// …else が無ければ falsy（＝ $x は非 null）がそのまま続く…
```

`if ($x === null) { return; }` の then 節は終わるので、if の後に残るのは **falsy 側**
（`$x` から null を除いた `int`）だけ。これで `$x + 1` が `int + int` になり、`$y` が
`int` と推論されます。

## `assert()` —— その場で絞り込む

`assert($x instanceof Foo)` は、以降のスコープで `$x` を狭めます。`if` を経由せず、文
そのものが絞り込みを起こします:

```php
if ($expr instanceof Expr\FuncCall && $expr->name->toLowerString() === 'assert') {
    $this->processNode($expr, $scope);
    return $this->typeSpecifier->specify($expr->args[0]->value, $scope)->truthy;
}
```

```php
assert(is_int($value));
$r = $value + 1; // $value は int → $r : int
```

## `match` の腕 —— S2 の宿題を回収

S2 で `match (true) { $x instanceof Foo => $x->bar() }` の腕を絞り込めず、自己解析に
叱られました。その宿題をここで回収します。各腕を、その条件で絞り込んだスコープで
解析します（[`processMatch`](../../../impls/looking-glass/04-control-flow/src/Analyser/NodeScopeResolver.php)）:

```php
$matchesTrue = $node->cond instanceof Expr\ConstFetch && $node->cond->name->toLowerString() === 'true';
foreach ($node->arms as $arm) {
    foreach ($arm->conds as $cond) {
        $specified = $matchesTrue
            ? $this->typeSpecifier->specify($cond, $remaining)              // match(true): 条件そのもの
            : $this->typeSpecifier->specifyEquality($node->cond, $cond, $remaining); // match($x): $x === 値
        $armScope = $specified->truthy;
        $remaining = $specified->falsy; // この腕に当たらなかった世界線を次へ
    }
    $this->processNode($arm->body, $armScope); // 絞り込まれたスコープで腕を解析
}
```

`if` の絞り込みを作っておいたので、`match` はそれを腕ごとに呼ぶだけ。部品が効いています。

```console
$ dev/bin/ministan analyse examples/looking-glass/narrowing.php
[OK] No errors   # $shape instanceof Circle の腕で $shape->radius() が誤検出されない
```

## 動かす

```console
$ dev/bin/ministan annotate examples/looking-glass/narrowing.php
    11  $y : int    ← 早期 return で $x: int
    20  $r : int    ← assert で $value: int
```

## まとめ

- **終わる枝（return/throw）を合流から外す**ことで、早期 return の後の絞り込みが効く
- `assert()` は文そのものが以降のスコープを狭める
- `match` の腕を条件で絞り込み、S2 の宿題を回収した
- いずれも Part 5 の `TypeSpecifier` 部品の組み合わせ —— 小さな部品が複雑な制御フローを支える

> 簡略化: ループの不動点解析（ループ本体を安定するまで再解析）と `match` **式の結果型**
> 推論は見送り。前者は到達不能解析、後者は腕ごとの絞り込み込みの評価が要ります。

次の S5 では、by-ref 出力引数（`preg_match($s, $m)` の `$m`）とスタブを扱います
（名前付き引数の型照合は S7 へ）。
