<?php

declare(strict_types=1);

namespace Ministan\Rules\Functions;

use Ministan\Analyser\Scope;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\Rules\ArgumentTypeChecker;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

/**
 * 関数呼び出しの実引数の型を、宣言されたパラメータ型と照合する。
 *
 * @implements Rule<FuncCall>
 */
final class FunctionCallParameterTypesRule implements Rule
{
    public function __construct(
        private readonly ArgumentTypeChecker $checker,
    ) {
    }

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof FuncCall);

        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if (!$node->name instanceof Name || $provider === null || !$provider->hasFunction($node->name->toString())) {
            return [];
        }

        $function = $provider->getFunction($node->name->toString());

        $errors = [];
        foreach ($this->checker->check($function->parameterTypes, $function->parameterNames, $node->args, $scope) as [$position, $expected, $actual]) {
            $errors[] = new RuleError(
                sprintf(
                    'Parameter #%d of function %s() expects %s, %s given.',
                    $position,
                    $function->name,
                    $expected->describe(),
                    $actual->describe(),
                ),
                $node->getStartLine(),
            );
        }

        return $errors;
    }
}
