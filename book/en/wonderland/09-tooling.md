# Part 9 — Making it a tool

> *The code for this chapter lives in the snapshot [`impls/wonderland/09-tooling`](../../../impls/wonderland/09-tooling) — a slice of the live `dev/` tree taken at `git tag part-09`.*

This is the last chapter of the basics volume, and the core of the type checker is done. What remains is the work that turns it into a **tool people can actually use** — directory recursion, multiple output formats, and a baseline.

## A whole directory at once

Until now we’ve fed it one file at a time, but in practice you point it at a directory
([`FileFinder`](../../../impls/wonderland/09-tooling/src/Analyser/FileFinder.php)):

```php
foreach ($iterator as $info) {
    if ($info->isFile() && $info->getExtension() === 'php') {
        $files[] = $info->getPathname();
    }
}
```

`Analyser::analyse(array $files)` then analyzes each file in turn and gathers the errors.

```console
$ dev/bin/ministan analyse dev/src
[OK] No errors
```

We can now run the analyzer over its own entire source tree with a single command.

## Swapping out the output — `ErrorFormatter`

A table for humans, and JSON for CI. We abstract the output behind an **interface**
([`ErrorFormatter`](../../../impls/wonderland/09-tooling/src/Output/ErrorFormatter.php)).
[`TableErrorFormatter`](../../../impls/wonderland/09-tooling/src/Output/TableErrorFormatter.php) groups by file, and
[`JsonErrorFormatter`](../../../impls/wonderland/09-tooling/src/Output/JsonErrorFormatter.php) emits something machine-readable:

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

## baseline — the first step into legacy code

Drop a type checker onto a large existing codebase and you get thousands of findings at
once. You can’t turn CI red until every last one is fixed. So we reach for a **baseline** —
freeze today’s findings as “already accepted,” and turn red **only for findings that are
newly introduced**
([`Baseline`](../../../impls/wonderland/09-tooling/src/Output/Baseline.php)). It’s both a
guardrail — nothing new slips in — and a restart button: **the cleanups you’d put off for fear
of touching old code can begin again**.

```console
$ dev/bin/ministan analyse --generate-baseline=ministan-baseline.json src
Baseline written to ministan-baseline.json (1234 errors).

$ dev/bin/ministan analyse --baseline=ministan-baseline.json src
[OK] No errors
```

The matching is a simplified version, keyed on the (file, message) pair — real PHPStan goes
as far as counting occurrences. With this, only the findings that are newly introduced light
up red.

> baseline isn’t the private property of PHPStan, the analyzer this book models itself on. In
> PHP, Psalm got there first and popularized the idea of “grandfathering” existing errors —
> leaving the pre-existing ones in place. The notion of **taking away the fear so improvement
> can resume** is shared property across PHP static analysis.

## Exit codes

For CI: `1` if there are findings, `0` if there are none. `0` when generating a baseline.
Combine it with `--error-format=json` and an editor or a CI pipeline can consume it cleanly.

## The basics volume, complete

We started from the single line `Hello, World.`, and here we are:

- **parse → scope propagation → type inference → narrowing → rule application → report**
- an immutable `Scope`, the algebra of `Type` and three-valued logic, `UnionType`,
  reflection, PHPDoc
- a usable tool with a level system and a baseline
- and, through the whole journey, **non-rejecting** — stay silent about what you don’t know

And now recall that screen from the opening of Part 0, the one we held up and said “this is
where we end up”:

```console
$ ministan analyse src/
 src/Foo.php:42
   Parameter #1 $name of function greet() expects string, int given.

 [ERROR] Found 1 error
```

ministan really prints this now. The argument-type mismatch is driven by the type inference
we stacked up across Parts 4–7 and the rules of Part 8 — we reached the goal we set out at
the very start, with our own hands.

ministan analyzes itself and passes clean. Small as it is, it’s a real static analyzer.

## What’s left, and what comes next (ministan Through PHP’s Looking-Glass)

The basics volume put “drive a single thread through the core, minimally” first, and left a
lot for later:

- full generics / template types (`@template T`) → S3
- constant array shapes and the type of array access → S2, loop type widening → S7
- by-ref output parameters (the `$m` in `preg_match($s, $m)`) → S5, named arguments → S7
- external signatures via stubs → S5 (namespace resolution of class names in PHPDoc waits
  even further into the advanced volume)
- the config file → S1, the result cache → S6 (parallel execution is the territory of a
  full implementation)

The advanced volume, **ministan Through PHP’s Looking-Glass**, steps into all of these.

## Summary

- Directory recursion, multiple formats, and a baseline finished it into a usable tool.
- Output is swappable behind an interface.
- The baseline is the first step into adopting an analyzer on legacy code.
- Across the ten chapters of the basics volume, we’ve made one full lap around the essence of
  PHPStan.
