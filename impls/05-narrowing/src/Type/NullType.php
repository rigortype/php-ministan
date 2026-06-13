<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

final class NullType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'null';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type)
            ?? ($type instanceof self ? TrinaryLogic::Yes : TrinaryLogic::No);
    }
}
