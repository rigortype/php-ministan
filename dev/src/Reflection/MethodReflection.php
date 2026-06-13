<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;

/**
 * メソッド 1 つのシグネチャ。名前・パラメータ型・戻り値型。
 */
final readonly class MethodReflection
{
    /**
     * @param list<Type> $parameterTypes
     */
    public function __construct(
        public string $name,
        public Type $returnType,
        public array $parameterTypes,
    ) {
    }

    public static function fromNode(ClassMethod $node, TypeNodeResolver $resolver): self
    {
        return new self(
            $node->name->toString(),
            $resolver->resolve($node->returnType),
            array_map(static fn ($param): Type => $resolver->resolve($param->type), $node->params),
        );
    }

    public static function fromNative(ReflectionMethod $method, TypeNodeResolver $resolver): self
    {
        return new self(
            $method->getName(),
            $resolver->resolveNative($method->getReturnType()),
            array_map(
                static fn ($param): Type => $resolver->resolveNative($param->getType()),
                $method->getParameters(),
            ),
        );
    }
}
