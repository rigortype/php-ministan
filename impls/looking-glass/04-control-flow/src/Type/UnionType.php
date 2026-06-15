<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * A union type meaning "one of these types". `int|string`, `Foo|null`, and so on.
 *
 * It arises where narrowing branches merge. A variable that became int in an `if`'s then and
 * string in its else becomes `int|string` after the merge. PHPStan's {@see \PHPStan\Type\UnionType}.
 *
 * Normalization (flattening, deduping, dropping never, collapsing to a single type when there is one
 * member) is the job of the producer, {@see TypeCombinator::union()}. Here we assume already-normalized members.
 */
final class UnionType implements Type
{
    /** @var list<Type> */
    private array $types;

    /**
     * @param list<Type> $types two or more members (already normalized)
     */
    public function __construct(array $types)
    {
        $this->types = $types;
    }

    /**
     * @return list<Type>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function describe(): string
    {
        $parts = array_map(static fn (Type $t): string => $t->describe(), $this->types);
        sort($parts); // sort for stable display

        return implode('|', $parts);
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        // If the operand is a union, gather the relation to each member and fold with extreme-identity
        // (all Yes→Yes, all No→No, a mixture is a partial match → Maybe).
        if ($type instanceof UnionType) {
            $results = [];
            foreach ($type->types as $member) {
                $results[] = $this->isSuperTypeOf($member);
            }

            return TrinaryLogic::extremeIdentity($results);
        }

        // For a single type, it suffices that any one member accepts it (OR).
        $result = TrinaryLogic::No;
        foreach ($this->types as $member) {
            $result = $result->or($member->isSuperTypeOf($type));
        }

        return $result;
    }

    public function accepts(Type $type): TrinaryLogic
    {
        if ($type instanceof UnionType) {
            $results = [];
            foreach ($type->types as $member) {
                $results[] = $this->accepts($member);
            }

            return TrinaryLogic::extremeIdentity($results);
        }

        $result = TrinaryLogic::No;
        foreach ($this->types as $member) {
            $result = $result->or($member->accepts($type));
        }

        return $result;
    }

    public function equals(Type $type): bool
    {
        if (!$type instanceof self || count($this->types) !== count($type->types)) {
            return false;
        }

        // Compare in an order-independent way.
        foreach ($this->types as $a) {
            foreach ($type->types as $b) {
                if ($b->equals($a)) {
                    continue 2;
                }
            }

            return false;
        }

        return true;
    }
}
