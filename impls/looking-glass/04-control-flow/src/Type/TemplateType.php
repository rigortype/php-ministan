<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * A type variable `T`. The generics "type not yet decided".
 *
 * Declared with `@template T` and **substituted** for a concrete type at call time.
 * Relation checks defer to the upper bound (default mixed), and identity is judged by name.
 */
final class TemplateType implements Type
{
    use SimpleTypeTrait;

    public function __construct(
        public readonly string $name,
        public readonly Type $bound,
    ) {
    }

    public function describe(): string
    {
        return $this->name;
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type) ?? $this->bound->isSuperTypeOf($type);
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->name === $this->name;
    }
}
