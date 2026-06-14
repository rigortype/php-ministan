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
 * キーごとに値の型が分かっている配列。`array{a: 1, b: 'x'}`。
 *
 * 配列リテラルから生まれる。`['id' => 42]` の型は `array<string, int>` ではなく
 * `array{id: 42}`。だから `$row['id']` がちょうど `42` だと分かる——定数型が配列にも
 * 効く、PHPStan の切れ味の核。
 */
final class ConstantArrayType implements Type
{
    /**
     * @param list<Type> $keyTypes   各要素のキー型（定数）
     * @param list<Type> $valueTypes 各要素の値型（$keyTypes と同じ並び）
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

    /** 指定オフセットの値型。定数キーが一致すればその値型、不明なら全値型の union。 */
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
