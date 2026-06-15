<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * The top type. Contains every value. Also the default that stands for "unknown".
 *
 * Under the non-rejecting philosophy, when inference fails we collapse to mixed rather than to
 * never or an exception. mixed accepts everything and is the supertype of everything.
 */
final class MixedType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'mixed';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return TrinaryLogic::Yes;
    }
}
