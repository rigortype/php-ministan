<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;

/**
 * The signature of a single method: name, parameter types, and return type.
 *
 * Types are decided as "PHPDoc if present, otherwise the native declaration." Like PHPStan,
 * PHPDoc is given priority because it can express more precise types than the native one,
 * such as `array<int, string>`.
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

    public static function fromNode(ClassMethod $node, TypeNodeResolver $resolver, PhpDocTypeResolver $phpDoc): self
    {
        $doc = $phpDoc->parse($node->getDocComment()?->getText());

        $parameterTypes = [];
        foreach ($node->params as $param) {
            $name = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : null;
            $parameterTypes[] = $name !== null && isset($doc->paramTypes[$name])
                ? $doc->paramTypes[$name]
                : $resolver->resolve($param->type);
        }

        return new self(
            $node->name->toString(),
            $doc->returnType ?? $resolver->resolve($node->returnType),
            $parameterTypes,
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
