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
     * @param list<bool> $byRefParams   whether each parameter is passed by reference (an output argument)
     */
    public function __construct(
        public string $name,
        public Type $returnType,
        public array $parameterTypes,
        public array $byRefParams = [],
    ) {
    }

    /**
     * @param list<string> $classTemplateNames the type variables the class declares (referenced by the method's @return T etc.)
     */
    public static function fromNode(ClassMethod $node, TypeNodeResolver $resolver, PhpDocTypeResolver $phpDoc, array $classTemplateNames = []): self
    {
        $doc = $phpDoc->parse($node->getDocComment()?->getText(), $classTemplateNames);

        $parameterTypes = [];
        $byRefParams = [];
        foreach ($node->params as $param) {
            $name = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : null;
            $parameterTypes[] = $name !== null && isset($doc->paramTypes[$name])
                ? $doc->paramTypes[$name]
                : $resolver->resolve($param->type);
            $byRefParams[] = $param->byRef;
        }

        return new self(
            $node->name->toString(),
            $doc->returnType ?? $resolver->resolve($node->returnType),
            $parameterTypes,
            $byRefParams,
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
            array_map(
                static fn ($param): bool => $param->isPassedByReference(),
                $method->getParameters(),
            ),
        );
    }
}
