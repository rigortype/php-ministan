<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\TrinaryLogic;

/**
 * A type representing an instance of some class or interface.
 *
 * Strengthened to handle inheritance in Part 6. It walks the class hierarchy via
 * {@see ReflectionProviderStaticAccessor} and decides `$child instanceof $parent` correctly as Yes/No.
 * When there is no provider, or the class is unknown, it collapses to Maybe (non-rejecting).
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

        return TrinaryLogic::Maybe; // if the hierarchy is unknown, neither narrow nor widen
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->className === $this->className;
    }
}
