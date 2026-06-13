<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * 配列型。キーと要素の型を持つ。`array<int, string>` や `list<Foo>`（= array<int, Foo>）。
 *
 * PHPDoc から生まれる最初の「複合型」。要素型を保つことで `foreach` の要素や配列アクセスの
 * 型推論につながる（本格化は応用編）。PHPStan の配列型を大きく簡略化したもの。
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
