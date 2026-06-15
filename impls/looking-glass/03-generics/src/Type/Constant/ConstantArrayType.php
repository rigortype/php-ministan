<?php

declare(strict_types=1);

namespace Ministan\Type\Constant;

use Ministan\TrinaryLogic;
use Ministan\Type\ArrayType;
use Ministan\Type\MixedType;
use Ministan\Type\SimpleTypeTrait;
use Ministan\Type\Type;
use Ministan\Type\TypeCombinator;

/**
 * An array whose value type is known per key. `array{a: 1, b: 'x'}`.
 *
 * It arises from array literals. The type of `['id' => 42]` is not `array<string, int>` but
 * `array{id: 42}`. That is why we know `$row['id']` is exactly `42` — constant types working on
 * arrays too is the core of PHPStan's sharpness.
 */
final class ConstantArrayType implements Type
{
    /**
     * @param list<Type> $keyTypes   the key type of each element (constant)
     * @param list<Type> $valueTypes the value type of each element (in the same order as $keyTypes)
     */
    public function __construct(
        private readonly array $keyTypes,
        private readonly array $valueTypes,
    ) {
    }

    use SimpleTypeTrait;

    public function describe(): string
    {
        if ($this->keyTypes === []) {
            return 'array{}';
        }

        $parts = [];
        foreach ($this->keyTypes as $i => $keyType) {
            $parts[] = sprintf('%s: %s', $this->keyLabel($keyType), $this->valueTypes[$i]->describe());
        }

        return 'array{' . implode(', ', $parts) . '}';
    }

    /** The value type at the given offset. If a constant key matches, its value type; otherwise the union of all value types. */
    public function getOffsetValueType(Type $offset): Type
    {
        foreach ($this->keyTypes as $i => $keyType) {
            if ($keyType->equals($offset)) {
                return $this->valueTypes[$i];
            }
        }

        return $this->getIterableValueType();
    }

    public function getIterableKeyType(): Type
    {
        return $this->keyTypes === [] ? new MixedType() : TypeCombinator::union(...$this->keyTypes);
    }

    public function getIterableValueType(): Type
    {
        return $this->valueTypes === [] ? new MixedType() : TypeCombinator::union(...$this->valueTypes);
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type)
            ?? match (true) {
                $type instanceof self => $this->equals($type) ? TrinaryLogic::Yes : TrinaryLogic::Maybe,
                $type instanceof ArrayType => TrinaryLogic::Maybe,
                default => TrinaryLogic::No,
            };
    }

    public function equals(Type $type): bool
    {
        if (!$type instanceof self || count($this->keyTypes) !== count($type->keyTypes)) {
            return false;
        }

        foreach ($this->keyTypes as $i => $keyType) {
            if (!$keyType->equals($type->keyTypes[$i]) || !$this->valueTypes[$i]->equals($type->valueTypes[$i])) {
                return false;
            }
        }

        return true;
    }

    private function keyLabel(Type $keyType): string
    {
        return match (true) {
            $keyType instanceof ConstantStringType => $keyType->value,
            $keyType instanceof ConstantIntegerType => (string) $keyType->value,
            default => $keyType->describe(),
        };
    }
}
