<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction;

/**
 * The signature of a single function. PHPDoc takes priority, with the native declaration below it.
 */
final readonly class FunctionReflection
{
    /**
     * @param list<Type>   $parameterTypes
     * @param list<string> $parameterNames used to match named arguments
     * @param list<bool>   $byRefParams   whether each parameter is passed by reference (an output argument)
     * @param list<string> $templateNames the type variables this function declares (@template)
     */
    public function __construct(
        public string $name,
        public Type $returnType,
        public array $parameterTypes,
        public array $parameterNames = [],
        public array $byRefParams = [],
        public array $templateNames = [],
    ) {
    }

    public static function fromNode(string $name, Function_ $node, TypeNodeResolver $resolver, PhpDocTypeResolver $phpDoc): self
    {
        $doc = $phpDoc->parse($node->getDocComment()?->getText());

        $parameterTypes = [];
        $parameterNames = [];
        $byRefParams = [];
        foreach ($node->params as $param) {
            $paramName = $param->var instanceof Variable && is_string($param->var->name) ? $param->var->name : '';
            $parameterTypes[] = $paramName !== '' && isset($doc->paramTypes[$paramName])
                ? $doc->paramTypes[$paramName]
                : $resolver->resolve($param->type);
            $parameterNames[] = $paramName;
            $byRefParams[] = $param->byRef;
        }

        return new self(
            $name,
            $doc->returnType ?? $resolver->resolve($node->returnType),
            $parameterTypes,
            $parameterNames,
            $byRefParams,
            $doc->templateNames,
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
            array_map(
                static fn ($param): string => $param->getName(),
                $function->getParameters(),
            ),
            array_map(
                static fn ($param): bool => $param->isPassedByReference(),
                $function->getParameters(),
            ),
        );
    }
}
