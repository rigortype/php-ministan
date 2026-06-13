<?php

declare(strict_types=1);

namespace Ministan\Rules\Methods;

use Ministan\Analyser\Scope;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use Ministan\Type\ObjectType;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

/**
 * 既知のクラスに対する、存在しないメソッド呼び出しを検出する。
 *
 * non-rejecting を厳守する。オブジェクトの型が確定した {@see ObjectType} で、その
 * クラスがリフレクションで引け、メソッドが確実に無く、`__call` も無い——この全部が
 * 揃ったときだけ報告する。少しでも不明なら黙る。
 *
 * @implements Rule<MethodCall>
 */
final class CallToUndefinedMethodRule implements Rule
{
    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof MethodCall);

        if (!$node->name instanceof Identifier) {
            return []; // 動的メソッド名 $obj->$m() は追わない
        }

        $objectType = $scope->getType($node->var);
        if (!$objectType instanceof ObjectType) {
            return []; // 型が確定していない → 黙る
        }

        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if ($provider === null || !$provider->hasClass($objectType->className)) {
            return [];
        }

        $class = $provider->getClass($objectType->className);
        $method = $node->name->toString();

        if ($class->hasMethod($method) || $class->hasMethod('__call')) {
            return [];
        }

        return [new RuleError(
            sprintf('Call to an undefined method %s::%s().', $class->name, $method),
            $node->getStartLine(),
        )];
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }
}
