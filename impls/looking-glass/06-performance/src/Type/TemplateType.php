<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * 型変数 `T`。ジェネリクスの「まだ決まっていない型」を表す。
 *
 * `@template T` で宣言され、呼び出し時に具体的な型へ **置換（substitution）** される。
 * 関係判定は上限境界（bound, 既定 mixed）に委ね、同一性は名前で見る。
 */
final class TemplateType implements Type
{
    use SimpleTypeTrait;

    public function __construct(
        public readonly string $name,
        public readonly Type $bound,
    ) {
    }

    public function describe(): string
    {
        return $this->name;
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return $this->relateToSpecial($type) ?? $this->bound->isSuperTypeOf($type);
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->name === $this->name;
    }
}
