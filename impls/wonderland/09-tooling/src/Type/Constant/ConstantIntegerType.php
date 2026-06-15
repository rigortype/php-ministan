<?php

declare(strict_types=1);

namespace Ministan\Type\Constant;

use Ministan\TrinaryLogic;
use Ministan\Type\IntegerType;
use Ministan\Type\SimpleTypeTrait;
use Ministan\Type\Type;

/**
 * The constant type (literal type) representing a single integer value. The type of `42` is not `int` but `42`.
 *
 * Constant types are precisely what makes PHPStan's inference sharp. Right after `$x = 42;`, the type
 * of $x is not `int` but `42`, so we can judge `match` exhaustiveness and even unreachable branches.
 */
final class ConstantIntegerType implements Type
{
    use SimpleTypeTrait;

    public function __construct(
        public readonly int $value,
    ) {
    }

    public function describe(): string
    {
        return (string) $this->value;
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type)
            ?? match (true) {
                $type instanceof self => $this->value === $type->value
                    ? TrinaryLogic::Yes
                    : TrinaryLogic::No,
                // A general int might happen to be this value.
                $type instanceof IntegerType => TrinaryLogic::Maybe,
                default => TrinaryLogic::No,
            };
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->value === $this->value;
    }
}
