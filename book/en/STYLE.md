# English edition — style & terminology guide

> This is a **contributor document**, not part of the reader's path. It records how the
> English edition of ministan is written so that every chapter reads as one book. If you
> are here to read ministan, start at [README.md](README.md).

The English edition is a **transcreation** of the Japanese original under
[`book/ja/`](../ja/README.md), not a literal translation. The Japanese is the source of
truth for *content* (what is built, in what order, with which code); the English re-authors
the *prose* so it reads as if written in English from the start. When the two would fight,
keep the content and rewrite the sentence.

## What is and isn't translated

ministan's running text is Japanese, but most of the artifacts around it are already
language-neutral, which keeps the surface area small:

- **Translated:** chapter prose, the front matter ([README.md](README.md)), the
  [glossary](glossary.md), figure captions/labels (the SVGs under
  [`figures/`](figures/)), the reference-reading notes (re-aimed for English readers — see
  below), and **the comments inside the code excerpts shown in the prose** (see the caveat
  below).
- **English already — shared as-is:** identifiers and type names (`Scope`, `Type`,
  `NodeScopeResolver`, …), the CLI output and diagnostic messages, **all code comments and
  docblocks**, the CLI `--help` text, and the example PHP under
  [`examples/`](../../examples). The English chapters link to the **same**
  [`impls/`](../../impls) tree as the Japanese ones — there is no per-language code fork.

> **Resolved (2026-06-15): the shared code is English.** The `dev/`, `examples/`, and
> `impls/` source — comments, docblocks, and user-facing CLI strings — is now **English**
> (it used to be Japanese). The code is the international default; each book then translates
> the comments **in the excerpts it prints**: `book/ja/` shows Japanese comments in its prose,
> `book/en/` shows English. Click-through to a snapshot shows English for both editions. So in
> English chapters, code excerpts are printed verbatim from the (English) snapshot; in
> Japanese chapters, the same excerpts carry hand-translated Japanese comments.

## Directory & path mapping

The English tree mirrors the Japanese one exactly:

| Japanese | English |
|----------|---------|
| `book/ja/README.md` | `book/en/README.md` |
| `book/ja/glossary.md` | `book/en/glossary.md` |
| `book/ja/wonderland/NN-*.md` | `book/en/wonderland/NN-*.md` |
| `book/ja/looking-glass/NN-*.md` | `book/en/looking-glass/NN-*.md` |
| `book/ja/figures/*.svg` | `book/en/figures/*.svg` |

Because the depth is identical (`book/en/wonderland/NN.md` is three levels deep), the
relative links are the same tokens as the Japanese side:

- Code snapshot: `../../../impls/wonderland/NN-*` (and `.../looking-glass/NN-*`).
- Figures: `../figures/NN-*.svg`.
- Sibling chapter / glossary / front matter: `02-scope.md`, `../glossary.md`, `../README.md`.

## Voice & tone

The Carroll framing is **not** a Japanese-ism to localize away — the two volume titles are
already English and land *better* in English:

- **ministan in PHP's Wonderland** (the basics) — Alice carrying a little logic into the
  loosely-typed wonderland of PHP.
- **ministan Through PHP's Looking-Glass** (the advanced volume) — the mirror world:
  reflection, and the harder sequel.

Hold the original's register:

- **Reference-book tone, not breezy tutorial.** Calm, precise, second-person plural ("we
  build…"). It explains *why* a design is the way it is, then builds it.
- **The spine is `non-rejecting`.** Every chapter ties back to it: don't flag working code;
  collapse the unknown to `mixed`; stay silent when unsure. Keep that thread explicit.
- **"Grow a working thing."** Like chibivue/chibirigor: start from one running line and add
  one capability per chapter. Keep the forward momentum ("next chapter we add…").
- **No theory as a prerequisite.** Type theory is introduced *as we build the thing that
  needs it*, never assumed. Keep equations out of the basics volume.
- Don't pad. The Japanese is dense and economical; match it. Transcreation means natural
  English, not *more* English.

## Terminology (canonical English)

Code identifiers (`Scope`, `Type`, `NodeScopeResolver`, `TrinaryLogic`, `RuleLevelHelper`,
…) are never translated — they are names in the codebase. The conceptual vocabulary:

| Concept | Canonical English | Notes |
|---------|-------------------|-------|
| 軸: 受理寄り | **non-rejecting** | the book's coined axis; keep verbatim, set in code-ish weight on first use |
| 抽象構文木 | **AST** / abstract syntax tree | spell out once on first use |
| 三値論理 | **three-valued logic** (`TrinaryLogic`) | Yes / Maybe / No |
| 部分型 | **subtype** / **supertype** (`isSuperTypeOf`) | "is a supertype of" |
| 定数型 | **constant type** | `42`, `'foo'`, `true` |
| 型推論 | **type inference** | local + declaration-driven, *not* whole-program |
| ユニオン型 | **union type** | `int\|string` |
| 絞り込み | **narrowing** (`TypeSpecifier`) | keep "narrowing"; the JP kept the English root too |
| リフレクション | **reflection** (`ReflectionProvider`) | |
| 漸進的型付け | **gradual typing** | Siek & Taha 2006 |
| constant array shape | **constant array shape** (`array{…}`) | |
| ジェネリクス | **generics** (`@template`) | "pseudo-generics": PHPDoc-level, erased at runtime |
| 置換 | **substitution** | one-directional; no bidirectional unification |
| スタブ | **stub** | a parsed PHPDoc-annotated declaration supplied from outside |
| by-ref 出力引数 | **by-ref output parameter** | like C# `out`/`ref` |
| 制御フロー絞り込み | **control-flow narrowing** | early return, `assert`, `match` arms |
| 結果キャッシュ | **result cache** | |
| 型ワイドニング | **type widening** / **loop widening** | fixed-point approximation over loops |
| 名前付き引数 | **named arguments** | |
| baseline / ignoreErrors / dogfood | **kept verbatim** | established English terms already |

When a term first appears, gloss it once (e.g., "narrowing — tightening a type per branch")
and then use it bare.

## House typography

- **Em dash:** a single em dash with surrounding spaces — ` — ` (U+2014). This mirrors the
  Japanese double-dash rhythm, wraps cleanly on screen, and is easy to verify. Do **not**
  use the unspaced `word—word` form or the en dash for this purpose. (The Japanese side uses
  a doubled `——`; English uses one.)
- **Quotes:** curly quotes “ ” and ‘ ’ in prose. Keep **straight** quotes inside code spans
  and inside raw-HTML attribute values — e.g. the `<picture>`/`<img>` figure embeds, whose
  `media="…"` / `src="…"` / `alt="…"` must stay straight or the image won't load. (When
  bulk-converting prose to curly quotes, protect both code spans and HTML tags.)
- **Code:** inline code in backticks; identifiers, file paths, and CLI text always in code.
- **Chapter labels:** "Part 0…Part 9" for Wonderland; "S1…S7" for Looking-Glass (matching
  the Japanese and the immutable git tags `part-NN` / `seasoned-NN`).

## Chapter header notes

Each chapter opens with the same two notes as the Japanese, transcreated:

1. **Snapshot note** (every chapter):
   `> *The code for this chapter lives in the snapshot [\`impls/<vol>/NN-*\`](../../../impls/<vol>/NN-*) (the live \`dev/\` tree sliced at \`git tag <tag>\`).`
2. **Further-reading note** (optional; only chapters with real type-theory content — skip
   the theory-light ones: Part 1, Part 9, S1, S6, mirroring the Japanese policy).

## Reference apparatus (re-aimed for English readers)

The Japanese edition pairs Pierce's TAPL with 遠藤侑介『型システムのしくみ』(*The Mechanics
of Type Systems*) — a build-a-**type-checker** companion that is **Japanese-only**, so an
English reader can't use it. The English edition does **not** force a substitute for it. There
is no English book of the same genre and calibre, and reaching for a build-an-*interpreter*
book (e.g. *Crafting Interpreters*) would be a false equivalence: ministan is about type
checking and type theory, not interpretation, so such a book contributes nothing on the actual
subject. The build-along *ethos* is in any case already credited to ministan's stated models,
chibivue and chibirigor. The apparatus is therefore lean and honest:

- **TAPL** — Benjamin C. Pierce, *Types and Programming Languages* (MIT Press). The shared
  type-theory reference; same chapter pointers as the Japanese edition.
- **Topical sources** where a chapter genuinely needs them: Siek & Taha, *Gradual Typing for
  Functional Languages* (2006) for the non-rejecting / gradual stance; the PHPStan blog
  (Ondřej Mirtes) and Psalm's design writing (Matt Brown) for analyzer design decisions.
- **Be honest about gaps.** Where 『しくみ』 was the *only* correspondence (e.g., its
  untagged-union treatment, or its "afterword" frontier), and no clean English equivalent
  exists, say so plainly rather than forcing a citation — the Japanese edition's candor
  about "no corresponding chapter" (e.g., flow analysis vs. the textbooks) is part of the
  tone.

## Sync with the Japanese source

`book/ja/` is the source of truth. When a Japanese chapter changes, re-transcreate the
matching English one rather than diffing word-for-word. Keep the English no farther behind
than one volume; note the last-synced Japanese commit in the PR/commit message when a
chapter lands.

## Review

The `ministan-review` skill currently targets `book/ja/`. An English review mode (the same
multi-lens battery — reproducibility, type-theory, PHPStan-fidelity, spec/src subset, plus
a native-English copyeditor in place of the Japanese one) is a follow-up; until then, run
the language-neutral lenses (reproducibility, fidelity, spec-subset, src-consistency) by
reading the English chapter directly.

## Per-chapter verification (prose-only changes)

English chapters are prose; they don't touch `dev/` or the tests, so the PHPUnit/dogfood
gate doesn't apply. After writing a chapter, check:

- **Links resolve** — every `](path)` target exists.
- **Em dash** — only the spaced ` — ` form; no `—word`/`word—`, no `――`/`─`/en-dash misuse.
- **Figures** — referenced SVGs exist under `figures/`; if you added one, render it to PNG
  (`inkscape --export-type=png --export-width=1000 -o out.png in.svg`) and eyeball it.
- **Terminology** — terms match the table above; code identifiers untranslated.
