# Glossary

The words that recur throughout the book, each tagged with the **chapter it first appears
in**. Come back whenever a term trips you up. The order is order-of-appearance — roughly the
order in which we build the things they name.

| Term | In one line | First seen |
|------|-------------|------------|
| **non-rejecting** | Accept any code that isn't a syntax error, and stay silent about what you can't be sure of (collapsing the unknown to `mixed`). The book's spine: never a false positive. | [Part 0](wonderland/00-overview.md) |
| **AST (abstract syntax tree)** | Source code represented as a tree rather than a string. `nikic/php-parser` builds it; all analysis runs over this tree. | [Part 1](wonderland/01-php-parser.md) |
| **`Rule`** | A checker for one kind of AST node that reports a problem when it finds one. Two methods: `getNodeType()` (which node) and `processNode()` (what to report). | [Part 1](wonderland/01-php-parser.md) |
| **`Scope`** | An immutable object holding "what we know right here." First a set of defined variables, later a variable-to-type environment. | [Part 2](wonderland/02-scope.md) |
| **`NodeScopeResolver`** | The recursive descent that walks the AST carrying a `Scope`, calling rules (and callbacks) at each point. The crux is telling a *read* context from a *write* context. | [Part 2](wonderland/02-scope.md) |
| **`Type`** | An algebraic object standing for "a set of values." Three methods ask how two types relate: `describe()`, `isSuperTypeOf()`, `accepts()`. | [Part 3](wonderland/03-type-system.md) |
| **`TrinaryLogic` (three-valued logic)** | Yes / Maybe / No. `mixed` is "maybe an int," and so on — it makes "perhaps" a first-class citizen, which becomes the axis of the level system. | [Part 3](wonderland/03-type-system.md) |
| **subtype (`isSuperTypeOf`)** | Containment, reading types as sets. `int ⊇ 42` is Yes; the reverse `42 ⊇ int` is Maybe (it is asymmetric). | [Part 3](wonderland/03-type-system.md) |
| **`mixed` / `never`** | `mixed` is the top type (every value; where the unknown collapses to). `never` is the bottom (the empty set). The two ends of the type lattice. | [Part 3](wonderland/03-type-system.md) |
| **constant type** | The type of a single value, like `42`, `'foo'`, or `true`. Right after `$x = 42`, the type is `42`, not `int` — this is where inference gets its edge. | [Part 3](wonderland/03-type-system.md) |
| **type inference (`Scope::getType`)** | Building a type bottom-up from the shape of an expression; `annotate` lets you peek at it. Not whole-program inference — propagation from declarations and expressions plus local narrowing. | [Part 4](wonderland/04-type-inference.md) |
| **`UnionType`** | "one of these types," `int\|string`. Born where branches of narrowing merge. `TypeCombinator` handles its creation and normalization. | [Part 5](wonderland/05-narrowing.md) |
| **narrowing (`TypeSpecifier`)** | Tightening a type per branch of a condition. From `instanceof` / `is_*` / `=== null` / `isset`, derive a `Scope` for the true case and one for the false. | [Part 5](wonderland/05-narrowing.md) |
| **reflection (`ReflectionProvider`)** | The window onto class, method, and function signatures. Two tiers: the analyzed code's own declarations, and native reflection. Used for `ObjectType` inheritance checks and return-type inference. | [Part 6](wonderland/06-reflection.md) |
| **PHPDoc** | Comment annotations — `@param` / `@return` / `@var` / `@template` — that express types more precisely than native declarations (they vanish at runtime; the analyzer is what gives them meaning). | [Part 7](wonderland/07-phpdoc.md) |
| **`RuleLevelHelper` / levels** | The machinery that decides, per level, whether a `Maybe` is reported or waved through. Higher levels add rules *and* grow stricter about `mixed` — two dials at once. | [Part 8](wonderland/08-rules-and-levels.md) |
| **baseline** | Freezing today's findings as "already accepted" so only newly introduced ones show red. It takes the fear out of adopting an analyzer on legacy code (Psalm led the way). | [Part 9](wonderland/09-tooling.md) |
| **dogfooding** | Running the analyzer on its own source. The book makes "passes with zero false positives" a pillar of quality; each hole it finds becomes the next chapter's homework. | [Part 9](wonderland/09-tooling.md) |
| **ignoreErrors** | A setting that drops findings matching a message regex. Where baseline silences a *place*, this silences a *kind*. | [S1](looking-glass/01-configuration.md) |
| **constant array shape (`array{…}`)** | An array whose value type is known per key. The type of `['id' => 42]` is `array{id: 42}`, not `array<string, int>`. | [S2](looking-glass/02-arrays.md) |
| **generics (`@template` / `TemplateType`)** | Pseudo-generics that express "a type not yet pinned down," `T`, via PHPDoc (Hack's lineage; Psalm pioneered it in PHP). Absent at runtime — they live only in the analyzer's layer. | [S3](looking-glass/03-generics.md) |
| **substitution** | Replacing a type variable `T` with a concrete type: `T → 42` in `identity(42)`, `T → int` in `Box<int>::get(): T`. One-directional assignment (no bidirectional unification). | [S3](looking-glass/03-generics.md) |
| **stub** | A file that supplies, from outside, types native reflection can't express — by **parsing** PHPDoc-annotated declarations (Psalm's `.phpstub` family). | [S5](looking-glass/05-byref-stubs.md) |
| **by-ref output parameter** | An argument passed by reference, like `$m` in `preg_match($s, $m)`. Since the function writes to it, treat it as a *definition*, not a read (PHP's analogue of C#'s `out`/`ref`). | [S5](looking-glass/05-byref-stubs.md) |
| **control-flow narrowing** | When the *shape* of the code tightens a type: a branch ending in an early return doesn't reach the merge, `assert(...)` narrows what follows, a `match` arm is narrowed by its condition. | [S4](looking-glass/04-control-flow.md) |
| **result cache** | Storing analysis results keyed by a file's content hash, so unchanged files aren't re-analyzed. A salt (version, level) invalidates it automatically when the logic changes. | [S6](looking-glass/06-performance.md) |
| **type widening (loop widening)** | Analyzing a loop body in two passes (a silent discovery pass, then the real one) to widen the types of variables that cross the loop — a fixed-point approximation. Types only ever widen, so the approximation suffices. | [S7](looking-glass/07-precision.md) |
| **named arguments** | Arguments passed by name, like `f(size: 'big')`. Type matching reverse-looks-up the declared position from the parameter name and lines them up. | [S7](looking-glass/07-precision.md) |
