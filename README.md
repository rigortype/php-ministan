# php-ministan

> Writing a PHP static analyzer: Step by Step, from one syntax error.

**ministan** is a tutorial that distills the essence of
[PHPStan](https://github.com/phpstan/phpstan) down to a minimal core and **rebuilds it from
scratch** on top of [nikic/php-parser](https://github.com/nikic/PHP-Parser). Following the
model of [chibivue](https://github.com/chibivue-land/chibivue) and
[chibirigor](https://github.com/rigortype/chibirigor), it grows a PHP static analyzer —
inference, narrowing, and type checking — one running step at a time, starting from a single
reported syntax error.

- Target: **PHP 8.3+**
- Built on **nikic/php-parser ^5**; PHPDoc via **phpstan/phpdoc-parser** (from Part 7)
- CLI: `ministan analyse <file>` (analyse) / `ministan annotate <file>` (show inferred types, from Part 4)

## Read the book

The book comes in two editions, built from the same code:

- 📖 **English — [`book/en/`](book/en/README.md)** — *ministan in PHP's Wonderland* (basics)
  and *Through PHP's Looking-Glass* (advanced). A transcreation, complete (Part 0–9 plus the
  seven advanced chapters S1–S7).
- 📖 **日本語 — [`book/ja/`](book/ja/README.md)** — the original edition, complete
  (Part 0–9 plus the seven advanced chapters S1–S7).

### How the editions differ

- **One project, one codebase.** Both editions describe the same analyzer and link to the
  same code under `dev/` and `impls/`. The code itself — identifiers, type names, comments,
  and CLI output — is English in both; only each book's prose is in its own language.
- **Japanese is the original; English is a transcreation** — re-authored to read naturally
  in English, not translated line by line.
- **The Japanese edition carries an extra reading guide.** Its optional *further-reading
  notes* point to type-system primers **published only in Japanese** — chiefly 遠藤侑介
  『型システムのしくみ』 (*The Mechanics of Type Systems*), a hands-on companion that
  implements a type checker — for readers who want to go a level deeper into the theory. The
  English edition keeps the shared English reference (Pierce's *Types and Programming
  Languages*) and topical English sources, but cannot offer those Japanese-only books.

## Design philosophy — non-rejecting

Like chibirigor, ministan **accepts any code that isn't a syntax error**. An unknown type
collapses to `mixed`, and the analyzer stays silent when it can't be sure — so it never emits
a false positive. This connects directly to PHPStan's rule levels and the semantics of
`mixed`.

## Layout

```
book/en/          Online book — English edition (transcreation; complete)
book/ja/          Online book — Japanese edition (original; complete)
dev/              Live implementation tree (grown chapter by chapter)
examples/         Sample PHP to analyse
impls/<vol>/NN    Per-chapter snapshots (self-contained Composer projects)
patches/          Chapter-to-chapter diffs that build impls/
tools/            Snapshot-generation scripts
CLAUDE.md         Contributor guide — conventions, workflow, gotchas
docs/             Contributor docs (e.g. the figures guide)
```

Development happens in the single `dev/` tree; chapter boundaries are marked with git tags
(`part-00`…`part-09`, `seasoned-01`…`seasoned-07`). The reader-facing `impls/<vol>/NN` are
**generated** from `dev/` + `patches/` by **`tools/build-impls.sh`**. See
[WORKFLOW.md](WORKFLOW.md).

```console
# Run the live tree
$ cd dev && composer install && cd ..
$ dev/bin/ministan analyse examples/hello.php

# Run any chapter's snapshot (self-contained)
$ cd impls/wonderland/02-scope && composer install
$ ./bin/ministan analyse examples/with-var-dump.php
```

## Curriculum

### ministan in PHP's Wonderland (basics)

| Part | Theme | What it builds |
|------|-------|----------------|
| 0 | The big picture and Hello World | Run the analysis pipeline end to end; report a syntax error |
| 1 | PHP-Parser and the AST | Syntax-based rules (the `Rule` interface) |
| 2 | Scope and tracking variables | Detect use of undefined variables |
| 3 | The type system, foundations | `Type`, three-valued logic, constant types |
| 4 | Type inference | Show inferred types with `annotate` |
| 5 | Unions and narrowing | Narrow on `instanceof` / `is_*` / `=== null` |
| 6 | Reflection | Return-type inference; detect undefined methods |
| 7 | PHPDoc | Turn `@param` / `@return` / `@var` into types |
| 8 | Rules and levels | Argument/return type mismatches; level 0–max |
| 9 | Becoming a tool | Recursive directories, JSON output, baseline |

### ministan Through PHP's Looking-Glass (advanced)

| Chapter | Theme | What it builds |
|---------|-------|----------------|
| S1 | Configuration and extension | NEON config, ignoreErrors, custom rules |
| S2 | Going deeper on arrays | Constant array shapes, array access, `foreach` element types |
| S3 | Generics | `@template`, type arguments, substituting type variables |
| S4 | Control flow and advanced narrowing | Early returns, `assert`, `match` arms |
| S5 | By-reference and stubs | By-ref output parameters; filling gaps with stubs |
| S6 | Performance | The result cache |
| S7 | Sharper inference and checking | `match` result types, union absorption, loop type widening, named arguments |

## License

This repository contains two kinds of material, each covered by its own
copyright and license.

### Prose and figures

The book text, figures, and other documentation under `book/ja/` and `book/en/`.

Copyright © 2026 USAMI Kenta. Licensed under
[Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)](https://creativecommons.org/licenses/by-sa/4.0/);
see [`LICENSE`](LICENSE) for the full text.

[![CC BY-SA 4.0](cc-by-sa.svg)](https://creativecommons.org/licenses/by-sa/4.0/)

> php-ministan © 2026 USAMI Kenta is licensed under [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/).

### Source code

The code under `dev/`, `impls/`, and `examples/` is a derivative work of
[phpstan/phpstan](https://github.com/phpstan/phpstan) and
[phpstan/phpstan-src](https://github.com/phpstan/phpstan-src), distributed
under their original MIT License:

```
MIT License

Copyright (c) 2016 Ondřej Mirtes
Copyright (c) 2025 PHPStan s.r.o.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
