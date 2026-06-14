<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionMethod;

/**
 * メソッド 1 つのシグネチャ。名前・パラメータ型・戻り値型。
 *
 * 型は「PHPDoc があれば PHPDoc、無ければネイティブ宣言」で決める。PHPStan と同じく
 * PHPDoc を上位に置くのは、`array<int, string>` のようにネイティブより精密に書けるから。
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

    /**
     * @param list<string> $classTemplateNames クラスが宣言する型変数（メソッドの @return T 等が参照する）
     */
    public static function fromNode(ClassMethod $node, TypeNodeResolver $resolver, PhpDocTypeResolver $phpDoc, array $classTemplateNames = []): self
    {
        $doc = $phpDoc->parse($node->getDocComment()?->getText(), $classTemplateNames);

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
