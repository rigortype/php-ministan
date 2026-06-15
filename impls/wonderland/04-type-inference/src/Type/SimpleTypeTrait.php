<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * Boilerplate shared by simple value types (int, string, null …).
 *
 * - `accepts` delegates to `isSuperTypeOf` (for these types, assignability = the subtype relation).
 *   Implicit conversions, such as float accepting int, are deferred to a refinement in a later chapter.
 * - `equals` checks whether the class is the same. Constant types also compare values, so they override it individually.
 * - `relateToTopAndBottom` returns the relation to the "top mixed, bottom never" that is common to every type.
 */
trait SimpleTypeTrait
{
    public function accepts(Type $type): TrinaryLogic
    {
        return $this->isSuperTypeOf($type);
    }

    public function equals(Type $type): bool
    {
        return $type::class === static::class;
    }

    /**
     * never is a subtype of every type (= always Yes). mixed could be any type (= Maybe).
     * If it is neither, return null and defer to the caller's own decision.
     */
    protected static function relateToTopAndBottom(Type $type): ?TrinaryLogic
    {
        if ($type instanceof NeverType) {
            return TrinaryLogic::Yes;
        }

        if ($type instanceof MixedType) {
            return TrinaryLogic::Maybe;
        }

        return null;
    }
}
