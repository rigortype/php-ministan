<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction;

/**
 * 関数 1 つのシグネチャ。
 */
final readonly class FunctionReflection
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

    public static function fromNode(string $name, Function_ $node, TypeNodeResolver $resolver): self
    {
        return new self(
            $name,
            $resolver->resolve($node->returnType),
            array_map(static fn ($param): Type => $resolver->resolve($param->type), $node->params),
        );
    }

    public static function fromNative(ReflectionFunction $function, TypeNodeResolver $resolver): self
    {
        return new self(
            $function->getName(),
            $resolver->resolveNative($function->getReturnType()),
            array_map(
                static fn ($param): Type => $resolver->resolveNative($param->getType()),
                $function->getParameters(),
            ),
        );
    }
}
