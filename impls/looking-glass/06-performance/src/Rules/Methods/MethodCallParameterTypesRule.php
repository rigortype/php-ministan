<?php

declare(strict_types=1);

namespace Ministan\Rules\Methods;

use Ministan\Analyser\Scope;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\Rules\ArgumentTypeChecker;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use Ministan\Type\ObjectType;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

/**
 * メソッド呼び出しの実引数の型を、宣言されたパラメータ型と照合する。
 *
 * @implements Rule<MethodCall>
 */
final class MethodCallParameterTypesRule implements Rule
{
    public function __construct(
        private readonly ArgumentTypeChecker $checker,
    ) {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof MethodCall);

        if (!$node->name instanceof Identifier) {
            return [];
        }

        $objectType = $scope->getType($node->var);
        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if (!$objectType instanceof ObjectType || $provider === null || !$provider->hasClass($objectType->className)) {
            return [];
        }

        $class = $provider->getClass($objectType->className);
        if (!$class->hasMethod($node->name->toString())) {
            return []; // 未定義メソッドは別ルールの担当
        }

        $method = $class->getMethod($node->name->toString());

        $errors = [];
        foreach ($this->checker->check($method->parameterTypes, $node->args, $scope) as [$position, $expected, $actual]) {
            $errors[] = new RuleError(
                sprintf(
                    'Parameter #%d of method %s::%s() expects %s, %s given.',
                    $position,
                    $class->name,
                    $method->name,
                    $expected->describe(),
                    $actual->describe(),
                ),
                $node->getStartLine(),
            );
        }

        return $errors;
    }
}
