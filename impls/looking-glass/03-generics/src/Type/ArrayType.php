<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;
use Ministan\Type\Constant\ConstantArrayType;

/**
 * Array type. Carries a key type and an item type. `array<int, string>` or `list<Foo>` (= array<int, Foo>).
 *
 * Keeping the item type lets us infer the type of `foreach` elements and array accesses. When the
 * value type is known per key, see {@see ConstantArrayType}. A heavy simplification of PHPStan's array types.
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

    public function getIterableKeyType(): Type
    {
        return $this->keyType;
    }

    public function getIterableValueType(): Type
    {
        return $this->itemType;
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        $special = $this->relateToSpecial($type);
        if ($special !== null) {
            return $special;
        }

        if ($type instanceof self) {
            return $this->keyType->isSuperTypeOf($type->keyType)
                ->and($this->itemType->isSuperTypeOf($type->itemType));
        }

        // A constant array is a subtype of a general array when its key/value unions fit.
        if ($type instanceof ConstantArrayType) {
            return $this->keyType->isSuperTypeOf($type->getIterableKeyType())
                ->and($this->itemType->isSuperTypeOf($type->getIterableValueType()));
        }

        return TrinaryLogic::No;
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self
            && $this->keyType->equals($type->keyType)
            && $this->itemType->equals($type->itemType);
    }
}
