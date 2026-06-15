<?php

declare(strict_types=1);

namespace Ministan\Type;

/**
 * A mapping from type-variable name to concrete type. Substitutes every {@see TemplateType} within a type at once.
 *
 * The heart of substitution: e.g. `identity(42)` builds `T → 42`, then resolves the return type `T`
 * to `42`. It recurses into compound types (union, array, generic).
 */
final class TemplateTypeMap
{
    /**
     * @param array<string, Type> $map
     */
    public function __construct(
        private readonly array $map,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->map === [];
    }

    public function resolve(Type $type): Type
    {
        if ($type instanceof TemplateType) {
            return $this->map[$type->name] ?? $type;
        }

        if ($type instanceof UnionType) {
            return TypeCombinator::union(...array_map($this->resolve(...), $type->getTypes()));
        }

        if ($type instanceof ArrayType) {
            return new ArrayType($this->resolve($type->keyType), $this->resolve($type->itemType));
        }

        if ($type instanceof GenericObjectType) {
            return new GenericObjectType($type->className, array_map($this->resolve(...), $type->typeArguments));
        }

        return $type;
    }
}
