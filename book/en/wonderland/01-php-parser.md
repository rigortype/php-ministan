# Part 1 — PHP-Parser and the AST

> *The code for this chapter lives in the snapshot [`impls/wonderland/01-php-parser`](../../../impls/wonderland/01-php-parser) — a slice of the live `dev/` tree taken at `git tag part-01`.*

In Part 0 we ran the two ends through — input (parsing) and output (reporting). This chapter
slips the first check in **between** them. The subject is something everyone has done at
least once: **a `var_dump()` left in the code**.

## The AST as our footing

Static analysis treats code not as a string but as a **structure — a tree**.
nikic/php-parser turns the source into an **abstract syntax tree (AST)** for us.

```php
var_dump($value);
```

becomes, roughly, this tree:

```
Stmt\Expression
└─ Expr\FuncCall
   ├─ name: Name("var_dump")
   └─ args: [ Arg(Expr\Variable("value")) ]
```

Our rule only has to look for “an `Expr\FuncCall` whose name is `var_dump`.” Unlike grepping
for `var_dump` with a regular expression, an AST never trips over a **`var_dump` sitting in a
comment or a string literal**. That is the worth of standing on top of a real parse.

## The `Rule` interface

PHPStan’s heart is `Rule`. ministan adopts the same shape, minimized
([`src/Rules/Rule.php`](../../../impls/wonderland/01-php-parser/src/Rules/Rule.php)):

```php
/** @template TNodeType of Node */
interface Rule
{
    /** @return class-string<TNodeType> */
    public function getNodeType(): string;

    /**
     * @param TNodeType $node
     * @return list<RuleError>
     */
    public function processNode(Node $node): array;
}
```

- `getNodeType()` — declares, by class name, “which node do I react to?”
- `processNode()` — receives that node and, if something is wrong, returns a `RuleError`.

> In Part 2, `processNode()` gains a `Scope` argument carrying “what we know at this point”
> (`processNode(Node $node, Scope $scope)`). Chapter by chapter, the rules grow a little
> smarter.

`@template` is the notation for **declaring a type variable in PHPDoc**. (The real treatment
of type variables comes in the advanced volume; here you can read it loosely, as a tie binding
“the type `getNodeType()` returns” to “the type `processNode()` receives.”) These
pseudo-generics — giving the code types in a docblock without touching PHP’s syntax —
originate in Hack, were pioneered in PHP by Psalm, and are used daily by PHPStan and everyone
else. They are also exactly the kind of code our analyzer will, in the end, learn to verify.

## The first rule

[`NoVarDumpRule`](../../../impls/wonderland/01-php-parser/src/Rules/Functions/NoVarDumpRule.php) uses no types at all. It is pure
syntactic pattern matching:

```php
/** @implements Rule<FuncCall> */
final class NoVarDumpRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node): array
    {
        assert($node instanceof FuncCall);

        // A dynamic call like $callback() has no statically known name → out of scope
        if (!$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'var_dump') {
            return [];
        }

        return [new RuleError('Called var_dump().', $node->getStartLine())];
    }
}
```

Already the **non-rejecting** philosophy shows its face. When `$node->name` is not a `Name`
(that is, a dynamic call), we **let it pass in silence**. We don’t make noise with “it might
be var_dump.” We strike only at the syntax we’re sure of.

> We don’t resolve namespaces yet, either. Whether `namespace Foo; var_dump()` falls back to
> the global function can only be decided once we know which functions exist — that is the job
> of Part 6, where we bring in reflection.

## Bundling the rules — `RuleRegistry`

There are thousands of nodes, and the rules will only multiply. Brute-forcing every node
against every rule is wasteful, so we **index by node kind**
([`RuleRegistry`](../../../impls/wonderland/01-php-parser/src/Rules/RuleRegistry.php)).

The trick at lookup time is to follow not just the node’s own class but its **parent classes
and implemented interfaces** as well:

```php
private function classHierarchy(Node $node): array
{
    $class = $node::class;

    return [
        $class,
        ...array_values(class_parents($class)),
        ...array_values(class_implements($class)),
    ];
}
```

Thanks to this, a rule aiming at the concrete `Expr\FuncCall` and a rule aiming at the
abstract `Expr` can coexist through the same mechanism. PHPStan’s `Registry` follows this
strategy too.

## Walking the AST — the Visitor

Last, we need something to visit the AST one node at a time and apply the rules. We ride on
php-parser’s `NodeVisitor` ([`RuleApplyingVisitor`](../../../impls/wonderland/01-php-parser/src/Analyser/RuleApplyingVisitor.php)):

```php
public function enterNode(Node $node): null
{
    foreach ($this->registry->getRulesFor($node) as $rule) {
        foreach ($rule->processNode($node) as $ruleError) {
            $this->errors[] = new Error($ruleError->message, $this->file, $ruleError->line);
        }
    }

    return null;
}
```

`Analyser` now just pours the parse result into this visitor and returns the `Error`s it
collected:

```php
$visitor = new RuleApplyingVisitor($this->registry, $file);
(new NodeTraverser($visitor))->traverse($ast);

return $visitor->getErrors();
```

> This `RuleApplyingVisitor` is the larva of what PHPStan calls `NodeScopeResolver`. For now
> it only “visits a node and applies rules,” but in Part 2 we grow it to carry a
> **`Scope` (the type of each variable at each point)** as it descends the tree.

## Run it

```console
$ dev/bin/ministan analyse examples/with-var-dump.php
 examples/with-var-dump.php:12
   Called var_dump().

 [ERROR] Found 1 error
```

Do try writing `var_dump` in a comment or a string — and watch it go unflagged.

## Summary

- Analysis runs over the **AST**, not the string.
- A `Rule` is two methods: `getNodeType()` (which node) and `processNode()` (what to report).
- `RuleRegistry` indexes rules by node kind and finds them by walking the class hierarchy.
- `RuleApplyingVisitor` walks the tree and applies the rules — the starting point of the
  future scope resolver.

In the next chapter, Part 2, we finally introduce **`Scope`**. Descending the tree, it carries
“what is assigned to this variable right now,” and we detect the **use of an undefined
variable**. This is the core of PHPStan’s level 0, and the gateway to type inference.
