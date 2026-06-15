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
 * Checks that the type of the expression being `return`ed conforms to the declared return type.
 *
 * The return type of the function/method we are currently inside is carried by
 * {@see Scope::getFunctionReturnType()} (set by {@see \Ministan\Analyser\NodeScopeResolver}
 * when entering the function body).
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
            return []; // outside any function (e.g. a top-level return)
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
