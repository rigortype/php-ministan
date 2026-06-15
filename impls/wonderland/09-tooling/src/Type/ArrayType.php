<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * Array type. Carries a key type and an item type. `array<int, string>` or `list<Foo>` (= array<int, Foo>).
 *
 * The first "compound type" born from PHPDoc. Keeping the item type lets us infer the type of `foreach`
 * elements and array accesses (fully developed in the Applied volume). A heavy simplification of PHPStan's array types.
 */
final class ArrayType implements Type
{
    use SimpleTypeTrait;

    public function __construct(
        public readonly Type $keyType,
        public readonly Type $itemType,
    ) {
    }

    public function describe(): string
    {
        return sprintf('array<%s, %s>', $this->keyType->describe(), $this->itemType->describe());
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type)
            ?? match (true) {
                $type instanceof self => $this->keyType->isSuperTypeOf($type->keyType)
                    ->and($this->itemType->isSuperTypeOf($type->itemType)),
                default => TrinaryLogic::No,
            };
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self
            && $this->keyType->equals($type->keyType)
            && $this->itemType->equals($type->itemType);
    }
}
