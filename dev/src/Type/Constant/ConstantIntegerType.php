<?php

declare(strict_types=1);

namespace Ministan\Type\Constant;

use Ministan\TrinaryLogic;
use Ministan\Type\IntegerType;
use Ministan\Type\SimpleTypeTrait;
use Ministan\Type\Type;

/**
 * 単一の整数値を表す定数型（リテラル型）。`42` の型は `int` ではなく `42`。
 *
 * 定数型こそ PHPStan の推論を鋭くする源。`$x = 42;` の直後、$x の型は `int` ではなく
 * `42` なので、`match` の網羅性や到達不能分岐まで判定できる。
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
                // 一般の int は、たまたまこの値かもしれない。
                $type instanceof IntegerType => TrinaryLogic::Maybe,
                default => TrinaryLogic::No,
            };
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->value === $this->value;
    }
}
