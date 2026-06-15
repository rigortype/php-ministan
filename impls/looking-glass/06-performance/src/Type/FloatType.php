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
        // The implicit int → float widening is handled in a later chapter as a refinement of accepts. Strict here.
        return $this->relateToSpecial($type)
            ?? ($type instanceof self ? TrinaryLogic::Yes : TrinaryLogic::No);
    }
}
