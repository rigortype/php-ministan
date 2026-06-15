# S7 — Sharper inference and checking

> *The code for this chapter lives in the snapshot [`impls/looking-glass/07-precision`](../../../impls/looking-glass/07-precision) — a slice of the live `dev/` tree taken at `git tag seasoned-07`.*

> **Further reading** (optional): subtype absorption in a union is the **join** over a lattice — the join (least upper bound) of TAPL’s subtyping (chapters 15–16). Loop type widening is a **fixed-point approximation**, the flavor of idea you meet in dataflow analysis and abstract interpretation. This chapter lives just *past* the type-theory textbook: it is about the precision you fill in after the rules are right.

By now the core runs end to end. This chapter is the finish — we go back and tighten the precision we kept waving off as “a simplification.” The result type of a `match` expression, subtype absorption inside a union, loop type widening, and type matching for named arguments.

## A union absorbs its subtypes

`int|0` ought to be just `int`. `0` is a subtype of `int`: `int.isSuperTypeOf(0)` is Yes — `int` contains `0` — and the wider type is the supertype. TypeScript folds unions like this automatically; ministan’s `mergeWith`, though, has only ever **dropped exact duplicates** and never folded subtypes, so redundant unions like `int|0` were left lying around. So we teach `TypeCombinator::union()` to **absorb subtypes** ([`TypeCombinator`](../../../impls/looking-glass/07-precision/src/Type/TypeCombinator.php)):

```php
// If an existing member is a supertype of $type, $type is redundant (given int, we don't need 0).
foreach ($result as $existing) {
    if ($existing->isSuperTypeOf($type)->yes()) {
        continue 2;
    }
}
// Conversely, if $type is the supertype, drop the existing members that are its subtypes.
$result = array_values(array_filter($result, fn ($e) => !$type->isSuperTypeOf($e)->yes()));
$result[] = $type;
```

Now a merge result no longer balloons, and the result type of a `match` and the like comes out tidy.

> **Note on the reading.** Order types by the subtype relation and you get a **lattice** (the type-lattice figure back in Part 3). A union is its **join** — the least upper bound — and since `mixed`, the top, absorbs anything, `int|mixed` collapses to `mixed`. The theory of join and meet is TAPL chapter 16, “Metatheory of Subtyping.”

## The result type of a `match` expression

In S4 we could narrow **within a `match` arm**, but the **type of the whole `match` expression** stayed `mixed`. Union the type of each arm’s body and you have it — with the twist that each arm is evaluated in the scope its own condition narrows ([`Scope::matchType`](../../../impls/looking-glass/07-precision/src/Analyser/Scope.php)):

```php
$armScope = $matchesTrue
    ? $specifier->specify($cond, $remaining)->truthy
    : $specifier->specifyEquality($expr->cond, $cond, $remaining)->truthy;
$armTypes[] = $armScope->getType($arm->body); // evaluate the body in the narrowed scope
// …union them at the end…
```

The arm `$shape instanceof Circle => $shape->radius()` resolves `radius(): int` in a world where `$shape` is narrowed to `Circle`, giving `int`. `default => 0` gives `0`. Union them and the result is `int`. With that, `area(): int` passes even at the strictest level 9.

## Loop type widening (a fixed-point approximation)

A loop body runs not once but many times around. If `$x` is reassigned inside the loop, then on the second lap `$x` should carry a type that reflects “the assignment from the previous lap.” We approximate this in **two passes** ([`analyseLoopBody`](../../../impls/looking-glass/07-precision/src/Analyser/NodeScopeResolver.php)):

```php
// 1. Walk the body silently (no rules fire), widening the types of variables that cross the loop.
$discovered = $this->silently(fn () => $this->processStmts($stmts, $entry));
$widened = $entry->mergeWith($discovered);
// 2. Analyse the body for real, once, in the widened scope (rules fire on this one pass only).
$result = $this->processStmts($stmts, $widened);
// The loop might run zero times. Merge the widened scope with the real result and return that.
return $widened->mergeWith($result);
```

The crux is the **silent pass**. So that rules never fire twice, the discovery pass switches the callbacks off for its duration. That way we can take the second lap’s types into account without reporting anything twice.

> Why is it safe to stop after two laps? Because each merge only ever widens a type, a later lap can’t undo an earlier widening; we stop after the second lap as a deliberate approximation — a true fixed point would keep iterating until nothing changes.

```php
$prev = 'start';
foreach ($items as $item) {
    $current = $prev;  // 'item'|'start' — on the previous lap $prev may have become 'item'
    $prev = 'item';
}
```

> **Note on the reading.** This approximation — “types only ever widen (they are monotone), so it bottoms out in finitely many steps” — is the idea behind the **widening** operator in **abstract interpretation**, program analysis’s standard trick for forcing a path to the fixed point. Ruby’s type analysis, TypeProf, is of the same lineage. It is a tool of the *analysis algorithm*, sitting outside the static typing rules that TAPL lays down — a frontier the type-theory textbook doesn’t reach.

## Type matching for named arguments

In S5 we set named arguments aside because “the positions break down.” Hand the parameter **names** to reflection, though, and we can reverse-look-up the position from the name ([`ArgumentTypeChecker`](../../../impls/looking-glass/07-precision/src/Rules/ArgumentTypeChecker.php)):

```php
$index = $arg->name !== null
    ? array_search($arg->name->toString(), $parameterNames, true) // name → position
    : $position;
```

```console
$ dev/bin/ministan analyse examples/looking-glass/precision.php
 …
   Parameter #2 of function box() expects int, 'big' given.   # box(size: 'big')
```

## Summary

- A union now **absorbs its subtypes**, so it can’t balloon (`int|0` → `int`).
- The result type of a `match` expression is the union of each arm’s body type, taken in the scope that arm’s condition narrows.
- A loop is analyzed in **two passes — a silent discovery pass plus the real one** — to widen the types that cross it.
- With parameter names carried on reflection, **named arguments** get type matching too.

> Simplifications that remain: a true fixed point (iterating to convergence), flow through array elements, intersection types, variance.

## End of the second volume — and on to PHPStan

From the single line `Hello, World.`, we have come all this way. The basics volume threaded the core — parse → scope → inference → narrowing → rules → report — and the advanced volume put working flesh on it: configuration (NEON) and extension, array types, generics, control-flow narrowing, by-ref parameters and stubs, the result cache, and now the sharpening of inference and checking.

ministan analyzes itself and passes clean. Small as it is, it is a real static analyzer, whole.

From here on — open [PHPStan’s source](https://github.com/phpstan/phpstan-src), and you should find that you can *read* it, class by class: “ah, so this is what ministan’s such-and-such becomes when you build it for real.” `Scope`, `Type`, `TypeSpecifier`, `NodeScopeResolver`, `RuleLevelHelper` — the names and the roles are continuous with ours, one unbroken ground.

Safe travels.
