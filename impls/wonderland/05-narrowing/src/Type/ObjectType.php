<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * あるクラス／インターフェイスのインスタンスを表す型。
 *
 * Part 5 では「クラス名」しか知らない素朴な実装。同名なら Yes、別名なら
 * 継承関係が不明なので Maybe を返す（non-rejecting）。`$x instanceof Foo` の
 * 絞り込みで使う。継承を踏まえた厳密な部分型判定は、リフレクションを得る
 * Part 6 で本 ObjectType を強化して実現する。
 */
final class ObjectType implements Type
{
    use SimpleTypeTrait;

    public function __construct(
        public readonly string $className,
    ) {
    }

    public function describe(): string
    {
        return $this->className;
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type)
            ?? match (true) {
                $type instanceof self => $this->className === $type->className
                    ? TrinaryLogic::Yes
                    : TrinaryLogic::Maybe, // 継承関係は Part 6 まで不明
                default => TrinaryLogic::No,
            };
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->className === $this->className;
    }
}
