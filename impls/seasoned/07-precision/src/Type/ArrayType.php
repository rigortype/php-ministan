<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;
use Ministan\Type\Constant\ConstantArrayType;

/**
 * 配列型。キーと要素の型を持つ。`array<int, string>` や `list<Foo>`（= array<int, Foo>）。
 *
 * 要素型を保つことで `foreach` の要素や配列アクセスの型推論につながる。キーごとに値型が
 * 分かる形は {@see ConstantArrayType}。PHPStan の配列型を大きく簡略化したもの。
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

        // 定数配列は、キー/値の union が適合すれば一般配列の部分型。
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
