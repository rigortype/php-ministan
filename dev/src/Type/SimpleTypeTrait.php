<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * Boilerplate shared by simple value types (int, string, null …).
 *
 * - `accepts` delegates to `isSuperTypeOf` (for these types, assignability = the subtype relation).
 * - `equals` checks whether the class is the same. Constant types also compare values, so they override it individually.
 * - `relateToSpecial` returns the relation to the cases common to every type: the top mixed, the bottom never, and union.
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
     * The relation to operands common to all types:
     * - never is a subtype of every type (= Yes)
     * - mixed could be any type (= Maybe)
     * - for a union, the extreme-identity of the relation to each member (all Yes→Yes, all No→No, mixed→Maybe)
     *
     * If it is none of these, return null and defer to the caller's own decision.
     */
    protected function relateToSpecial(Type $type): ?TrinaryLogic
    {
        if ($type instanceof NeverType) {
            return TrinaryLogic::Yes;
        }

        if ($type instanceof MixedType) {
            return TrinaryLogic::Maybe;
        }

        if ($type instanceof UnionType) {
            // A partial match (only some members fit) becomes Maybe rather than collapsing to No.
            // Otherwise we would falsely flag a partially-unfitting union at a low level (only blame it at a high level).
            $results = [];
            foreach ($type->getTypes() as $member) {
                $results[] = $this->isSuperTypeOf($member);
            }

            return TrinaryLogic::extremeIdentity($results);
        }

        return null;
    }
}
