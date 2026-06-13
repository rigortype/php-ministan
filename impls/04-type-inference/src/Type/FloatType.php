<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

final class FloatType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'float';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        // int → float の暗黙拡大は accepts の精密化として後の章で扱う。ここでは厳密。
        return self::relateToTopAndBottom($type)
            ?? ($type instanceof self ? TrinaryLogic::Yes : TrinaryLogic::No);
    }
}
