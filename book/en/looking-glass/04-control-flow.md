# S4 — Control flow and advanced narrowing

> *The code for this chapter lives in the snapshot [`impls/looking-glass/04-control-flow`](../../../impls/looking-glass/04-control-flow) — a slice of the live `dev/` tree taken at `git tag seasoned-04`.*

> **Further reading** (optional): the nearest neighbour here is **flow analysis** (dataflow analysis), a program-analysis topic — *not* a type-theory one. There is no corresponding chapter in TAPL, and the type-theory textbooks have nothing direct to say about it: pushing narrowing out across the whole control-flow graph is an implementation craft rather than a typing rule. We keep the citation honest and leave it there.

Narrowing isn’t confined to the inside of an `if` branch. After an **early return**, after `assert()`, inside the arms of a `match` — the *shape* of the code tightens a type. This chapter builds that sense of control flow.

## Early return — a branch that ends doesn’t reach the merge

This is one of the most common patterns in PHP:

```php
function plus_one(?int $x): int
{
    if ($x === null) {
        return 0;
    }
    $y = $x + 1; // here $x must be int — if it were null we'd have returned above
    return $y;
}
```

The key is this: **a branch that ends in `return` or `throw` never flows into the world after the `if`.** In `processIf` we judge whether a branch terminates and leave the terminating ones out of the merge ([`NodeScopeResolver`](../../../impls/looking-glass/04-control-flow/src/Analyser/NodeScopeResolver.php)):

```php
$thenScope = $this->processStmts($node->stmts, $specified->truthy);
if (!$this->alwaysTerminates($node->stmts)) {
    $endScopes[] = $thenScope; // a terminating branch doesn't join the merge
}
// …with no else, the falsy side ($x is non-null) simply continues…
```

The then-block of `if ($x === null) { return; }` terminates, so all that survives past the `if` is the **falsy side** — `$x` with `null` subtracted, an `int`. Now `$x + 1` is `int + int`, and `$y` is inferred as `int`.

## `assert()` — narrowing on the spot

`assert($x instanceof Foo)` narrows `$x` in the scope that follows. There’s no `if` in the way: the statement itself triggers the narrowing.

```php
if ($expr instanceof Expr\FuncCall && $expr->name->toLowerString() === 'assert') {
    $this->processNode($expr, $scope);
    return $this->typeSpecifier->specify($expr->args[0]->value, $scope)->truthy;
}
```

```php
assert(is_int($value));
$r = $value + 1; // $value is int → $r : int
```

## `match` arms — collecting on S2’s homework

Back in S2, we couldn’t narrow the arm in `match (true) { $x instanceof Foo => $x->bar() }`, and self-analysis called us out for it. We collect on that homework here. Each arm is analyzed in a scope narrowed by its own condition ([`processMatch`](../../../impls/looking-glass/04-control-flow/src/Analyser/NodeScopeResolver.php)):

```php
$matchesTrue = $node->cond instanceof Expr\ConstFetch && $node->cond->name->toLowerString() === 'true';
foreach ($node->arms as $arm) {
    foreach ($arm->conds as $cond) {
        $specified = $matchesTrue
            ? $this->typeSpecifier->specify($cond, $remaining)              // match(true): the condition itself
            : $this->typeSpecifier->specifyEquality($node->cond, $cond, $remaining); // match($x): $x === value
        $armScope = $specified->truthy;
        $remaining = $specified->falsy; // carry the value this arm didn't match on to the next arm
    }
    $this->processNode($arm->body, $armScope); // analyze the arm in the narrowed scope
}
```

Because we’d already built `if`-narrowing, `match` just calls it once per arm. Again, small parts holding up complex behavior.

```console
$ dev/bin/ministan analyse examples/looking-glass/narrowing.php
[OK] No errors   # in the $shape instanceof Circle arm, $shape->radius() raises no false positive
```

## Run it

```console
$ dev/bin/ministan annotate examples/looking-glass/narrowing.php
    11  $y : int    ← early return makes $x: int
    20  $r : int    ← assert makes $value: int
```

## Summary

- **Leaving terminating branches (return / throw) out of the merge** is what makes narrowing after an early return work.
- `assert()` narrows the scope that follows it — the statement itself does the work, with no `if` needed.
- We narrow each `match` arm by its condition, collecting on S2’s homework.
- All three are just combinations of the `TypeSpecifier` parts from Part 5 — small parts holding up complex control flow.

> Simplifications: we’re leaving loop fixed-point analysis (re-analyzing the loop body until it stabilizes) and inference of the **result type of a `match` *expression*** for later. The first needs reachability analysis; the second needs evaluating each arm with its narrowing folded in.

Next, in S5, we take on by-ref output parameters (the `$m` in `preg_match($s, $m)`) and stubs — type matching for named arguments waits until S7.
