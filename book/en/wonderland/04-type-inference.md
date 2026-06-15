# Part 4 — Type inference and `annotate`

> *The code for this chapter lives in the snapshot [`impls/wonderland/04-type-inference`](../../../impls/wonderland/04-type-inference) — a slice of the live `dev/` tree taken at `git tag part-04`.*

> **Further reading** (optional): TAPL, ch. 8 (“Typed Arithmetic Expressions”). The rules that give an expression a type — for literals and for operators — are exactly what we assemble here, one rule at a time. (Pierce’s text and ministan share the same move: a typing rule per syntactic form.)

Part 3 gave us the vocabulary of types. Part 2 gave us the vessel that tracks variables. This
chapter joins the two. We grow `Scope` from “is this variable defined?” into **“what type is
this variable?”**, and we implement `Scope::getType()`, which **infers** the type of any
expression. Then we add `ministan annotate`, a way to *see* what was inferred. This is the
first chapter where the type system visibly comes to life.

## `Scope` carries types

The contents that were `array<string, true>` (the set of defined variables) become
`array<string, Type>` (variable → type). That single change turns `Scope` into a type
environment.

```php
// src/Analyser/Scope.php
public function getVariableType(string $name): Type
{
    return $this->variableTypes[$name] ?? new MixedType(); // undefined collapses to mixed
}
```

Looking up an undefined variable throws nothing and returns `mixed` — that is the
non-rejecting way. Part 2’s undefined-variable detection (`hasVariable`) stays exactly where it
was; the *type* query always has an answer to give.

## `getType()` — the heart of inference

`Scope::getType(Expr): Type` is our counterpart to PHPStan’s central `Scope::getType()`. It
builds a type up from the structure of the expression:

> “Inference” here means **assembling a type bottom-up** from literals, declared types, and the
> shape of an expression. It is *not* the whole-program inference of the ML family, where a
> function’s type is an unknown to be **solved** for. What we can’t determine, we don’t solve —
> we collapse it to `mixed`. That is the non-rejecting way.

```php
public function getType(Expr $expr): Type
{
    return match (true) {
        $expr instanceof Scalar\Int_    => new ConstantIntegerType($expr->value), // 42 → 42
        $expr instanceof Scalar\String_ => new ConstantStringType($expr->value),  // 'x' → 'x'

        $expr instanceof Expr\Variable  => is_string($expr->name)
            ? $this->getVariableType($expr->name)
            : new MixedType(),

        $expr instanceof Expr\BinaryOp\Concat => new StringType(),            // . is always string
        $expr instanceof Expr\BinaryOp\Plus,
        $expr instanceof Expr\BinaryOp\Minus,
        $expr instanceof Expr\BinaryOp\Mul    => $this->arithmeticType($expr), // arithmetic

        $expr instanceof Expr\BinaryOp\Identical,
        /* …comparison, logical… */
        $expr instanceof Expr\BooleanNot      => new BooleanType(),           // comparisons are always bool

        default => new MixedType(), // when in doubt, mixed
    };
}
```

> **A note for the reader:** each arm of this `match` *is* a **typing rule** from type theory.
> The arm that looks a variable up in the environment is **T-Var** (TAPL ch. 9, Figure 9-1); the
> arms for literals and arithmetic are the typing rules of TAPL ch. 8 (the rules that hand a
> literal its type, as in `42 : int`). Drop the rules into the `match` one by one and the type
> system on paper lines up one-to-one with the code.

The payoff is that literals become **constant types** — `42` is not `int`, it is
`42`. So `annotate` shows `$a : 42`, and that precision becomes the foundation for deciding
`match` exhaustiveness (a later chapter).

Arithmetic is assembled with the type lattice from Part 3. If both sides are `int`, the result
is `int`; if both are numeric and either is `float`, the result is `float`; otherwise `mixed`:

```php
private function arithmeticType(Expr\BinaryOp $expr): Type
{
    $int = new IntegerType();
    if ($int->isSuperTypeOf($this->getType($expr->left))->yes()
        && $int->isSuperTypeOf($this->getType($expr->right))->yes()
    ) {
        return new IntegerType();
    }
    // …if either side is float then float, otherwise mixed…
}
```

> ministan does **not** do constant folding (inferring `42 + 1` as `43`). The type of `$a + 1`
> stays `int` — folding would weigh the analysis down for a payoff that is limited (real PHPStan
> does fold it, all the way to `43`).

## Binding a type at assignment

`NodeScopeResolver` now infers the right-hand side at every assignment and binds it to the
variable:

```php
private function processAssign(Expr\Assign $node, Scope $scope): Scope
{
    $scope = $this->processNode($node->expr, $scope);  // analyse the right-hand side
    $type  = $scope->getType($node->expr);             // infer its type
    return $this->processAssignTarget($node->var, $type, $scope); // bind it to the variable
}
```

Parameters get their types from their declarations as well (`int $n` → `IntegerType`). Class
types, nullables, and unions we collapse to `mixed` until Parts 6–7, where reflection and
PHPDoc come in.

## Generalizing the walk — the node callback

To build `annotate`, we reworked `NodeScopeResolver`. Where it used to carry rule application
directly, we **generalized it into a callback invoked with `(node, scope)` at each node**.
PHPStan’s `NodeScopeResolver` takes the same shape (its `nodeCallback`).

```php
public function __construct(callable $nodeCallback) { /* … */ }
```

- `analyse` passes a callback that runs the rules ([`Analyser`](../../../impls/wonderland/04-type-inference/src/Analyser/Analyser.php)).
- `annotate` passes a callback that collects types ([`AnnotateCommand`](../../../impls/wonderland/04-type-inference/src/Command/AnnotateCommand.php)).

The same scope propagation is shared by two purposes. The callback fires **before** each node
is processed, so at an assignment node the scope passed in is still the pre-binding one, and the
right-hand side infers cleanly:

```php
new NodeScopeResolver(function (Node $node, Scope $scope) use (&$rows): void {
    if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable) {
        $rows[] = [$node->getStartLine(), '$' . $node->var->name,
                   $scope->getType($node->expr)->describe()];
    }
});
```

## Run it

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

`$a` is the constant type `42`; `$b = $a + 1` is `int` (not folded); `$d` is `string` from the
concatenation; `$e` is `bool` from the comparison. Inside the function, `'n=' . $n` infers as
`string` from the `int` parameter `$n`, and the type flows all the way through to `return`.

## Summary

- Grow `Scope` into a **variable → type** environment, and infer an expression’s type with
  `getType()`.
- Literals become **constant types**; operators are assembled from Part 3’s type lattice (no
  constant folding).
- An assignment binds the right-hand side’s type to the variable. The unknown collapses to
  `mixed` (non-rejecting).
- Generalize `NodeScopeResolver` into a **node callback**, shared by `analyse` and `annotate`.
- `ministan annotate` makes the inference visible.

In the next chapter, Part 5, we **narrow** types. How does a condition like
`if ($x instanceof Foo)`, `is_int($x)`, or `$x === null` tighten the type of `$x` inside the
branch? We introduce `UnionType` and sharpen `Scope::mergeWith()` from a “fall back to `mixed`”
into a proper merge.
