<?php

declare(strict_types=1);

namespace Ministan\Rules\Functions;

use Ministan\Analyser\Scope;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use Ministan\Rules\RuleLevelHelper;
use Ministan\Type\NullType;
use PhpParser\Node;
use PhpParser\Node\Stmt\Return_;

/**
 * `return` する式の型が、宣言された戻り値型に適合するか検査する。
 *
 * 現在いる関数／メソッドの戻り値型は {@see Scope::getFunctionReturnType()} が運ぶ
 * （関数本体に入るとき {@see \Ministan\Analyser\NodeScopeResolver} が設定する）。
 *
 * @implements Rule<Return_>
 */
final class FunctionReturnTypeRule implements Rule
{
    public function __construct(
        private readonly RuleLevelHelper $ruleLevelHelper,
    ) {
    }

    public function getNodeType(): string
    {
        return Return_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof Return_);

        $declared = $scope->getFunctionReturnType();
        if ($declared === null) {
            return []; // 関数の外（トップレベルの return 等）
        }

        $returned = $node->expr === null ? new NullType() : $scope->getType($node->expr);

        if ($this->ruleLevelHelper->isAcceptable($declared, $returned)) {
            return [];
        }

        return [new RuleError(
            sprintf(
                'Should return %s but returns %s.',
                $declared->describe(),
                $returned->describe(),
            ),
            $node->getStartLine(),
        )];
    }
}
