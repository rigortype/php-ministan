<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction;

/**
 * 関数 1 つのシグネチャ。PHPDoc を上位、ネイティブ宣言を下位に置く。
 */
final readonly class FunctionReflection
{
    /**
     * @param list<Type>   $parameterTypes
     * @param list<string> $parameterNames 名前付き引数の照合に使う
     * @param list<bool>   $byRefParams   各パラメータが参照渡し（出力引数）か
     * @param list<string> $templateNames この関数が宣言する型変数（@template）
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
