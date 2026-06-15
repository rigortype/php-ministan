<?php

declare(strict_types=1);

namespace Ministan\Rules\Variables;

use Ministan\Analyser\Scope;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

/**
 * Detects a **read** of an undefined variable. A flagship of PHPStan level 0.
 *
 * This rule knows nothing about tree traversal. It only asks "is this variable listed as defined
 * in the {@see Scope} at this point?" Contextual judgments such as distinguishing a read from a
 * write, or whether we are inside an isset(), are handled by
 * {@see \Ministan\Analyser\NodeScopeResolver}, and this rule is handed a Variable node only at a
 * "read location that should be reported".
 *
 * @implements Rule<Variable>
 */
final class UndefinedVariableRule implements Rule
{
    public function getNodeType(): string
    {
        return Variable::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof Variable);

        // A variable variable $$name has no statically determined name -> do not follow (non-rejecting).
        if (!is_string($node->name)) {
            return [];
        }

        if ($scope->hasVariable($node->name)) {
            return [];
        }

        return [new RuleError(
            sprintf('Undefined variable: $%s', $node->name),
            $node->getStartLine(),
        )];
    }
}
