<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\BooleanType;
use Ministan\Type\Constant\ConstantBooleanType;
use Ministan\Type\FloatType;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\NeverType;
use Ministan\Type\NullType;
use Ministan\Type\ObjectType;
use Ministan\Type\StringType;
use Ministan\Type\Type;
use Ministan\Type\TypeCombinator;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType as UnionTypeNode;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Maps type "declarations" onto {@see Type}: `int` => IntegerType, `?Foo` => Foo|null, and so on.
 *
 * It handles two kinds of input:
 * - php-parser's type nodes (declarations in the code under analysis)
 * - PHP's native {@see ReflectionType} (signatures of built-in and external classes)
 */
final class TypeNodeResolver
{
    public function resolve(?Node $node): Type
    {
        return match (true) {
            $node === null => new MixedType(),
            $node instanceof Identifier => $this->fromKeyword($node->toLowerString()),
            $node instanceof Name => new ObjectType(ltrim($node->toString(), '\\')),
            $node instanceof NullableType => TypeCombinator::union($this->resolve($node->type), new NullType()),
            $node instanceof UnionTypeNode => TypeCombinator::union(
                ...array_map($this->resolve(...), $node->types),
            ),
            $node instanceof IntersectionType => new MixedType(), // intersection types are a simplification
            default => new MixedType(),
        };
    }

    public function resolveNative(?ReflectionType $type): Type
    {
        if ($type instanceof ReflectionUnionType) {
            return TypeCombinator::union(...array_map($this->resolveNative(...), $type->getTypes()));
        }

        if ($type instanceof ReflectionNamedType) {
            $resolved = $type->isBuiltin()
                ? $this->fromKeyword(strtolower($type->getName()))
                : new ObjectType(ltrim($type->getName(), '\\'));

            return $type->allowsNull() && $type->getName() !== 'null' && $type->getName() !== 'mixed'
                ? TypeCombinator::union($resolved, new NullType())
                : $resolved;
        }

        return new MixedType();
    }

    private function fromKeyword(string $keyword): Type
    {
        return match ($keyword) {
            'int' => new IntegerType(),
            'string' => new StringType(),
            'float' => new FloatType(),
            'bool' => new BooleanType(),
            'true' => new ConstantBooleanType(true),
            'false' => new ConstantBooleanType(false),
            'null', 'void' => new NullType(),
            'never' => new NeverType(),
            // array / iterable / object / callable / self / static / parent / mixed are refined in a later chapter
            default => new MixedType(),
        };
    }
}
