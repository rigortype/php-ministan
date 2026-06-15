<?php

declare(strict_types=1);

namespace Ministan\Type;

/**
 * The home for operations that combine and decompose types. PHPStan's {@see \PHPStan\Type\TypeCombinator}.
 *
 * These live here rather than on the type classes themselves because `union`/`remove` are cross-cutting
 * operations that "normalize across multiple types". Each type only needs to know its own relations.
 */
final class TypeCombinator
{
    /**
     * Union several types together and normalize.
     * Flatten → drop never → absorb mixed → dedupe → 0 members is never, 1 member is the single type.
     */
    public static function union(Type ...$types): Type
    {
        $flattened = [];
        foreach ($types as $type) {
            if ($type instanceof UnionType) {
                foreach ($type->getTypes() as $member) {
                    $flattened[] = $member;
                }
            } else {
                $flattened[] = $type;
            }
        }

        $result = [];
        foreach ($flattened as $type) {
            if ($type instanceof NeverType) {
                continue; // never does not contribute to a union
            }
            if ($type instanceof MixedType) {
                return new MixedType(); // mixed absorbs everything
            }

            foreach ($result as $existing) {
                if ($existing->equals($type)) {
                    continue 2; // drop duplicates
                }
            }
            $result[] = $type;
        }

        return match (count($result)) {
            0 => new NeverType(),
            1 => $result[0],
            default => new UnionType(array_values($result)),
        };
    }

    /**
     * Remove from $from the part contained in $typeToRemove.
     * Example: remove(int|null, null) = int. Used for narrowing in the else branch.
     */
    public static function remove(Type $from, Type $typeToRemove): Type
    {
        if ($from instanceof UnionType) {
            $kept = [];
            foreach ($from->getTypes() as $member) {
                if (!$typeToRemove->isSuperTypeOf($member)->yes()) {
                    $kept[] = $member;
                }
            }

            return self::union(...$kept);
        }

        if ($typeToRemove->isSuperTypeOf($from)->yes()) {
            return new NeverType();
        }

        return $from;
    }
}
