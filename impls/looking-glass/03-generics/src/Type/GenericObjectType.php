<?php

declare(strict_types=1);

namespace Ministan\Type;

/**
 * An object type carrying type arguments. `Collection<int>`, `Result<string, Error>`.
 *
 * Because it extends {@see ObjectType}, the "look at the class" work — detecting undefined methods,
 * checking arguments, and so on — keeps working as is, ignoring the type arguments. The type
 * arguments are used for substituting type parameters in method return types.
 */
final class GenericObjectType extends ObjectType
{
    /**
     * @param list<Type> $typeArguments
     */
    public function __construct(
        string $className,
        public readonly array $typeArguments,
    ) {
        parent::__construct($className);
    }

    public function describe(): string
    {
        $args = implode(', ', array_map(static fn (Type $t): string => $t->describe(), $this->typeArguments));

        return $this->className . '<' . $args . '>';
    }

    public function equals(Type $type): bool
    {
        if (!$type instanceof self
            || $type->className !== $this->className
            || count($type->typeArguments) !== count($this->typeArguments)
        ) {
            return false;
        }

        foreach ($this->typeArguments as $i => $argument) {
            if (!$argument->equals($type->typeArguments[$i])) {
                return false;
            }
        }

        return true;
    }
}
