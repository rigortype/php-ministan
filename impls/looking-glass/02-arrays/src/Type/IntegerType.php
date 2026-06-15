<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;
use Ministan\Type\Constant\ConstantIntegerType;

final class IntegerType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'int';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type)
            ?? (($type instanceof self || $type instanceof ConstantIntegerType)
                ? TrinaryLogic::Yes   // both int and a constant int like 42 are subtypes of int
                : TrinaryLogic::No);
    }
}
