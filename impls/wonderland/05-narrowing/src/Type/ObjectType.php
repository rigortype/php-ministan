<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * A type representing an instance of some class or interface.
 *
 * In Part 5 this is a naive implementation that knows only the "class name". For the same name it
 * returns Yes; for a different name the inheritance relationship is unknown, so it returns Maybe
 * (non-rejecting). Used for narrowing in `$x instanceof Foo`. Strict subtype decisions that account
 * for inheritance are achieved in Part 6, where reflection becomes available and this ObjectType is strengthened.
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
                    : TrinaryLogic::Maybe, // the inheritance relationship is unknown until Part 6
                default => TrinaryLogic::No,
            };
    }

    public function equals(Type $type): bool
    {
        return $type instanceof self && $type->className === $this->className;
    }
}
