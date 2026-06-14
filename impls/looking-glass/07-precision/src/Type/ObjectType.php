<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\TrinaryLogic;

/**
 * あるクラス／インターフェイスのインスタンスを表す型。
 *
 * Part 6 で継承対応に強化。{@see ReflectionProviderStaticAccessor} 経由でクラス階層を
 * 引き、`$child instanceof $parent` を正しく Yes/No 判定する。provider が無い、または
 * クラスが未知のときは Maybe に縮退（non-rejecting）。
 */
class ObjectType implements Type
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
                $type instanceof self => $this->isSuperTypeOfClass($type->className),
                default => TrinaryLogic::No,
            };
    }

    private function isSuperTypeOfClass(string $other): TrinaryLogic
    {
        if (strcasecmp($this->className, $other) === 0) {
            return TrinaryLogic::Yes;
        }

        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if ($provider !== null && $provider->hasClass($other)) {
            return $provider->getClass($other)->isSubclassOf($this->className)
                ? TrinaryLogic::Yes
                : TrinaryLogic::No;
        }

        return TrinaryLogic::Maybe; // 階層が分からなければ狭めも広げもしない
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->className === $this->className;
    }
}
