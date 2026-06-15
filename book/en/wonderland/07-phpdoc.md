# Part 7 — PHPDoc

> *The code for this chapter lives in the snapshot [`impls/wonderland/07-phpdoc`](../../../impls/wonderland/07-phpdoc) — a slice of the live `dev/` tree taken at `git tag part-07`.*

> **Further reading** (optional): declaring types *apart from the code* is a cousin of Ruby’s **RBS**. The array shape `array<K, V>` is the “records” of TAPL §11.8 (ch. 11). The `@template` seed connects to generics; there is no English build-a-type-checker companion to point at, so we’ll develop it in place in the advanced volume.

PHP’s native type declarations have a ceiling. You can write `array`, but not *an array of what*.

```php
/**
 * @param list<string> $names
 * @return array<int, string>
 */
function shout(array $names): array { /* … */ }
```

This **PHPDoc** is exactly what makes static analysis of PHP practical. In this chapter we
bring in `phpstan/phpdoc-parser` and take `@param` / `@return` / `@var` in as types.

> Unlike TypeScript, PHP’s generics (`array<int, string>`, `list<T>`, `@template T`) **exist
> only in PHPDoc**. At runtime they are gone; what the PHP runtime sees is a plain `array` or
> `object`. Reading the type information and giving it meaning is the analyzer’s job — PHPStan’s,
> ministan’s — which means a PHPDoc type is not a “runtime guarantee” but a “declaration to the
> analyzer.”

> **Reference note:** *where* you write a type differs by language — TypeScript puts it in the
> code itself (`x: number`), Ruby’s **RBS** in a *separate file* (`.rbs`), PHP’s PHPDoc in a
> *comment*. The place differs, but the shape is the same: declare a type in a layer apart from
> the body, and let the analyzer read it. A language with types built into the code doesn’t make
> this separation its subject — but for us, facing RBS and PHPDoc, it is the central theme.

## Wiring up phpdoc-parser

We don’t write the parser for type “strings” ourselves; we hand it to PHPStan’s official
[phpstan/phpdoc-parser](https://github.com/phpstan/phpdoc-parser).
[`PhpDocTypeResolver`](../../../impls/wonderland/07-phpdoc/src/Reflection/PhpDocTypeResolver.php)
converts the type AST it returns into ministan’s `Type`
(the counterpart to PHPStan’s `TypeNodeResolver`):

```php
private function toType(TypeNode $node): Type
{
    return match (true) {
        $node instanceof IdentifierTypeNode => $this->fromIdentifier($node->name),
        $node instanceof NullableTypeNode   => TypeCombinator::union($this->toType($node->type), new NullType()),
        $node instanceof UnionTypeNode      => TypeCombinator::union(...array_map($this->toType(...), $node->types)),
        $node instanceof GenericTypeNode    => $this->fromGeneric($node), // array<…>, list<…>
        $node instanceof ArrayTypeNode      => new ArrayType(new MixedType(), $this->toType($node->type)), // T[]
        default => new MixedType(),
    };
}
```

The `TypeCombinator::union()` we built in Part 5 carries `?int` straight through to `int|null`.
The small parts keep stacking up.

## The gateway to array shapes — `ArrayType`

PHPDoc gives rise to our first **compound type**: an
[`ArrayType`](../../../impls/wonderland/07-phpdoc/src/Type/ArrayType.php) carrying a key type and
an item type:

```php
final class ArrayType implements Type
{
    public function __construct(
        public readonly Type $keyType,
        public readonly Type $itemType,
    ) {}

    public function describe(): string
    {
        return sprintf('array<%s, %s>', $this->keyType->describe(), $this->itemType->describe());
    }
}
```

`array<int, string>` maps over as-is; `list<string>` becomes `array<int, string>`; `string[]`
becomes `array<mixed, string>`. Keeping the item type is what leads, in the advanced volume, to
inferring array accesses.

## PHPDoc takes precedence over the native declaration

`@param` / `@return` are taken into reflection. The iron rule is
**“PHPDoc if there is PHPDoc, the native declaration otherwise”**
([`MethodReflection`](../../../impls/wonderland/07-phpdoc/src/Reflection/MethodReflection.php)):

```php
$doc = $phpDoc->parse($node->getDocComment()?->getText());
// …
return new self(
    $node->name->toString(),
    $doc->returnType ?? $resolver->resolve($node->returnType), // PHPDoc takes precedence
    $parameterTypes,
);
```

A native declaration that can only say `array` is overwritten by a precise PHPDoc that says
`array<int, string>`. It’s the same reason PHPStan does it.

## `@var` — declaring a type on the spot

A `/** @var int $count */` riding on an assignment overwrites the inferred result. The
`NodeScopeResolver` reads the docblock when it processes the assignment
([`processExpressionStmt`](../../../impls/wonderland/07-phpdoc/src/Analyser/NodeScopeResolver.php)):

```php
$parsed = $this->phpDocTypeResolver->parse($doc->getText());
$varType = $parsed->varTypes[$expr->var->name] ?? $parsed->varTypes[''] ?? null;
if ($varType !== null) {
    $scope = $this->processNode($expr, $scope);          // the ordinary assignment handling
    return $scope->assignVariable($expr->var->name, $varType); // overwritten by @var
}
```

## Run it

```console
$ dev/bin/ministan annotate examples/phpdoc.php
examples/phpdoc.php
    14  $result : array<int, string>   ← from @return
    17  $count  : int                   ← @var overwrites mixed
```

`shout()`’s return value comes out as `array<int, string>`, and the `mixed` that `compute()`
returns is pinned to `int` by `@var int`.

## Summary

- Parsing type strings is handed to `phpstan/phpdoc-parser`; we convert the result into `Type`.
- `@param` / `@return` are taken into reflection **in preference to** the native declaration.
- `@var` overwrites the inferred result at the point of assignment.
- `ArrayType` opens the gateway to `array<K, V>` / `list<T>` / `T[]`.
- Namespace resolution of class names, and full-blown array-shape inference, are for the
  advanced volume.

In the next chapter, Part 8, we add the first rules that put all this type information to real
use. We implement `RuleLevelHelper`, the detection of **argument / return-value type
mismatches**, and PHPStan’s signature feature — the **level system** (the mechanism that turns
the strictness up by degrees, from 0 to max).
