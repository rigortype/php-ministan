<?php

declare(strict_types=1);

namespace Ministan\Type\Constant;

use Ministan\TrinaryLogic;
use Ministan\Type\SimpleTypeTrait;
use Ministan\Type\StringType;
use Ministan\Type\Type;

/**
 * 単一の文字列値を表す定数型。`'foo'` の型は `string` ではなく `'foo'`。
 */
final class ConstantStringType implements Type
{
    use SimpleTypeTrait;

    public function __construct(
        public readonly string $value,
    ) {
    }

    public function describe(): string
    {
        return "'" . $this->value . "'";
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return self::relateToTopAndBottom($type)
            ?? match (true) {
                $type instanceof self => $this->value === $type->value
                    ? TrinaryLogic::Yes
                    : TrinaryLogic::No,
                $type instanceof StringType => TrinaryLogic::Maybe,
                default => TrinaryLogic::No,
            };
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->value === $this->value;
    }
}
