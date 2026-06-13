<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;
use Ministan\Type\Constant\ConstantBooleanType;

final class BooleanType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'bool';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return self::relateToTopAndBottom($type)
            ?? (($type instanceof self || $type instanceof ConstantBooleanType)
                ? TrinaryLogic::Yes
                : TrinaryLogic::No);
    }
}
