<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * The bottom type. Contains no value at all (never happens).
 *
 * The return type of a function that only `throw`s, or the type of an unreachable branch. It is a
 * subtype of every type, and the only supertype it has is never. The polar opposite of mixed.
 */
final class NeverType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'never';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        // The only supertype of never is never. Even against mixed the answer is No.
        return $type instanceof self ? TrinaryLogic::Yes : TrinaryLogic::No;
    }
}
