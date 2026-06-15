<?php

declare(strict_types=1);

namespace Ministan\Type\Constant;

use Ministan\TrinaryLogic;
use Ministan\Type\BooleanType;
use Ministan\Type\SimpleTypeTrait;
use Ministan\Type\Type;

/**
 * The constant type representing `true` / `false`. It takes the lead role in condition narrowing (Part 5).
 */
final class ConstantBooleanType implements Type
{
    use SimpleTypeTrait;

    public function __construct(
        public readonly bool $value,
    ) {
    }

    public function describe(): string
    {
        return $this->value ? 'true' : 'false';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type)
            ?? match (true) {
                $type instanceof self => $this->value === $type->value
                    ? TrinaryLogic::Yes
                    : TrinaryLogic::No,
                $type instanceof BooleanType => TrinaryLogic::Maybe,
                default => TrinaryLogic::No,
            };
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->value === $this->value;
    }
}
