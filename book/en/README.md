# ministan — building a PHP static analyzer from scratch

> Writing a PHP static analyzer: Step by Step, from one syntax error.

A tutorial that distills the essence of [PHPStan](https://github.com/phpstan/phpstan) down
to a minimal core and, on top of [nikic/php-parser](https://github.com/nikic/PHP-Parser),
grows a PHP static analyzer — inference, narrowing, type checking, and all — **from a single
line that reports a syntax error.**

We start by analyzing the one line `Hello, World.`, and chapter by chapter we track
variables, infer types, narrow them, resolve generics, and finish with a small analyzer that
**analyzes itself and passes** (dogfooding). By the time you're done, the real PHPStan source
should read like, "ah — that's the serious version of the thing in ministan."

## About this book

From *using* a static analyzer to *building* one — that is the aim of this book.

**Who it's for**

- Working PHP developers who write types (`int $x`, `: string`, typed properties, enums,
  `readonly` as a matter of course).
- People who use PHPStan or Psalm "sort of," but want to know **how the insides actually
  work**.
- People who've never met a static analyzer but, writing type annotations, wonder: "who
  checks these, and how?"

**What it does not assume**

- No type theory required. What you need, you learn by **building it**, in the chapter that
  needs it.
- No mathematical rigor demanded either (the basics volume stays gentle; a little formality
  shows up in the advanced one).

> Lost on a word? The [glossary](glossary.md) tags each term with the chapter it first
> appears in.

## Setup

- **PHP 8.3 or newer** (the text uses 8.3's features without apology) and **Composer**.
- The base is `nikic/php-parser`. From the PHPDoc chapter we add `phpstan/phpdoc-parser`;
  from the configuration chapter, `nette/neon`. Each chapter's code runs with nothing but
  `composer install`.

## Further reading (optional)

For readers who want to peek one level deeper into type theory, related chapters carry an
optional **further-reading note**. None of it is required — the book stands on its own, and
because we don't want to presume type theory, none of it is mixed into the main thread:

- **TAPL** … Benjamin C. Pierce,
  *[Types and Programming Languages](https://www.cis.upenn.edu/~bcpierce/tapl/)* (MIT Press).
  The shared reference for type theory.

ministan's **non-rejecting** stance sits close to **gradual typing** (Siek & Taha, 2006): it
deliberately gives up a measure of soundness (TAPL ch. 8, §8.3) to avoid false positives —
more in the chapter notes.

## Design philosophy — non-rejecting

There is one axis to promise up front:

> **Accept any code that isn't a syntax error.** Collapse a type you can't determine to
> `mixed`, and stay silent about what you can't be sure of.

An analyzer that piles up "not sure, so I'll flag it anyway" is one nobody uses. Putting
**no false positives** first — that promise echoes through every chapter, as the level
system and as the semantics of `mixed`. More in [Part 0](wonderland/00-overview.md).

## The map — two volumes

### ministan in PHP's Wonderland (the basics) — run the thread through

One straight road — parse → scope → inference → narrowing → rules → report — run end to end
at minimal core.

| Part | Theme | What it builds |
|------|-------|----------------|
| [0](wonderland/00-overview.md) | The big picture and Hello World | Run the analysis pipeline end to end; report a syntax error |
| [1](wonderland/01-php-parser.md) | PHP-Parser and the AST | Syntax-based rules (the `Rule` interface) |
| [2](wonderland/02-scope.md) | Scope and tracking variables | Detect use of undefined variables |
| [3](wonderland/03-type-system.md) | The type system, foundations | `Type`, three-valued logic, constant types |
| [4](wonderland/04-type-inference.md) | Type inference | Show inferred types with `annotate` |
| [5](wonderland/05-narrowing.md) | Unions and narrowing | Narrow on `instanceof` / `is_*` / `=== null` |
| [6](wonderland/06-reflection.md) | Reflection | Return-type inference; detect undefined methods |
| [7](wonderland/07-phpdoc.md) | PHPDoc | Turn `@param` / `@return` / `@var` into types |
| [8](wonderland/08-rules-and-levels.md) | Rules and levels | Argument/return type mismatches; level 0–max |
| [9](wonderland/09-tooling.md) | Becoming a tool | Recursive directories, JSON output, baseline |

### ministan Through PHP's Looking-Glass (the advanced volume) — putting on flesh

Building on the basics, we add the precision and machinery that pay off in the field (a
volume that raises the difficulty on purpose).

| S | Theme | What it builds |
|---|-------|----------------|
| [1](looking-glass/01-configuration.md) | Configuration and extension | NEON config, ignoreErrors, custom rules |
| [2](looking-glass/02-arrays.md) | Going deeper on arrays | Constant array shapes, array access, `foreach` element types |
| [3](looking-glass/03-generics.md) | Generics | `@template`, type arguments, substituting type variables |
| [4](looking-glass/04-control-flow.md) | Control flow and advanced narrowing | Early returns, `assert`, `match` arms |
| [5](looking-glass/05-byref-stubs.md) | By-reference and stubs | By-ref output parameters; filling gaps with stubs |
| [6](looking-glass/06-performance.md) | Performance | The result cache |
| [7](looking-glass/07-precision.md) | Sharper inference and checking | `match` result types, union absorption, loop type widening, named arguments |

## How to read — `dev/` and `impls/`

The code the text links to is each chapter's finished **snapshot**,
[`impls/NN-*`](../../impls) — the code as it stood when that chapter was written. Any chapter
runs on its own:

```console
$ cd impls/wonderland/02-scope && composer install
$ ./bin/ministan analyse examples/with-var-dump.php
 examples/with-var-dump.php:12
   Called var_dump().

 [ERROR] Found 1 error
```

To see the final form — later chapters included — there's [`dev/`](../../dev), the live tree
grown through every chapter.

## Beyond — toward the real PHPStan

ministan is a minimal core. The names and the roles — `Scope`, `Type`, `TypeSpecifier`,
`NodeScopeResolver`, `RuleLevelHelper` — are continuous with the real
[PHPStan source](https://github.com/phpstan/phpstan-src). When you've finished, open the real
thing — it won't frighten you anymore.

So: on to [Part 0](wonderland/00-overview.md). Enjoy the trip.
