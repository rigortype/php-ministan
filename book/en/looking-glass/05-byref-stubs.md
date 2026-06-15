# S5 — By-reference and stubs

> *The code for this chapter lives in the snapshot [`impls/looking-glass/05-byref-stubs`](../../../impls/looking-glass/05-byref-stubs) — a slice of the live `dev/` tree taken at `git tag seasoned-05`.*

> **Further reading** (optional): a **stub** declares a type in a file *separate from the body* — the same idea as Ruby’s **RBS** (`.rbs`) and TypeScript’s **`.d.ts`**, and one more answer to Part 7’s question of *where* you write a type. It sits off to the side of type theory proper, but no checker aimed at a real language can do without it. The **by-ref output parameter** is PHP’s version of C#’s `out`/`ref` — an argument the callee writes *into*. C and C++ express the same idea through pointers; Java has no equivalent.

Back in basics Part 8, ministan analyzed its own source and flagged this:

```php
preg_match('/\d+/', $input, $matches);
//                           ^^^^^^^^ Undefined variable: $matches
```

`$matches` is a **by-ref output parameter** — `preg_match` writes the captured groups into it. But to the analyzer it looked like a *read*, so it cried “undefined.” This chapter clears that false positive, and while we’re here, adds a way to **supply missing signatures from outside** — **stubs**.

## Teaching reflection about by-reference

First we give reflection a notion of *which parameters are passed by reference*. For native functions that comes from `ReflectionParameter::isPassedByReference()`; for the analyzed code’s own declarations it comes from `Param::$byRef` ([`FunctionReflection`](../../../impls/looking-glass/05-byref-stubs/src/Reflection/FunctionReflection.php)):

```php
array_map(static fn ($param): bool => $param->isPassedByReference(), $function->getParameters());
```

## An output argument is a *definition*

Until now, every call argument was processed as a *read*. When a variable lands in a by-reference position, that is a **write — a definition**. We give calls dedicated handling in `NodeScopeResolver` and define the variable for exactly those arguments ([`processCallArgs`](../../../impls/looking-glass/05-byref-stubs/src/Analyser/NodeScopeResolver.php)):

```php
if (($byRef[$position] ?? false) && $arg->value instanceof Expr\Variable) {
    $scope = $scope->assignVariable($arg->value->name, new MixedType()); // define the output argument
} else {
    $scope = $this->processNode($arg->value, $scope);                    // an ordinary read
}
```

```console
$ dev/bin/ministan analyse examples/looking-glass/byref-stubs.php
[OK] No errors   # $matches is no longer treated as undefined
```

That collects the homework left over from basics Part 8.

## Stubs — supplying a signature from outside

Some types native reflection simply can’t express. The return type of `explode()` is, natively, `array` (which in ministan means `mixed`) — but it really is `list<string>`. PHPStan and Psalm fill this gap with **stubs** — PHPDoc-annotated declarations parsed but never run. Psalm popularized the approach with its `.phpstub` files; ministan takes the same route.

[`stubs/core.php`](../../../impls/looking-glass/05-byref-stubs/stubs/core.php) is **never executed**. It is **parsed**, purely to read its signatures:

```php
/** @return list<string> */
function explode(string $separator, string $string, int $limit = PHP_INT_MAX): array {}
```

At startup, [`ReflectionProvider`](../../../impls/looking-glass/05-byref-stubs/src/Reflection/ReflectionProvider.php) reads these and **prefers them over native reflection**:

```php
// priority: the analyzed declaration > the stub > native reflection
return $this->functions[$key] ?? $this->stubFunctions[$key] ?? /* native */;
```

```console
$ dev/bin/ministan annotate examples/looking-glass/byref-stubs.php
    14  $parts : array<int, string>   ← from the stub’s list<string>
    15  $first : string                ← $parts[0]
```

`list<string>` — a string array with sequential integer keys — maps in ministan to `array<int, string>` (the `ArrayType` rendering from basics Part 7). So `$parts` comes out as `array<int, string>`, and its element `$parts[0]` as `string`.

## Summary

- Give reflection a notion of by-reference parameters, and treat a variable in such a position as a **definition** rather than a read.
- With that, the `$m` in `preg_match(..., $m)` is no longer flagged as undefined (the basics Part 8 homework, collected).
- **Stubs** supply the signatures native reflection lacks, and take priority over native reflection.
- A stub is never run, only **parsed** — a PHPDoc-annotated declaration that supplies a signature from outside (the same idea as Psalm’s `.phpstub`).

> Simplifications: we skip type-matching for named arguments (mapping them back to positions), and refining the *output* type of a by-reference argument (turning `@param string[] $matches` into the type of `$matches`).

In S6 we implement the **result cache** that makes all of this usable on a large codebase for real.
