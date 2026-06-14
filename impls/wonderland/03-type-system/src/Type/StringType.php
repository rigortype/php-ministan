<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;
use Ministan\Type\Constant\ConstantStringType;

final class StringType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'string';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return self::relateToTopAndBottom($type)
            ?? (($type instanceof self || $type instanceof ConstantStringType)
                ? TrinaryLogic::Yes
                : TrinaryLogic::No);
    }
}
