<?php

declare(strict_types=1);

namespace Ministan\Rules\Functions;

use Ministan\Analyser\Scope;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

/**
 * The first "real" rule: it detects a `var_dump()` debugging call that was left in by mistake.
 *
 * A pure syntactic pattern match that uses no types at all. Even this alone makes a useful lint.
 *
 * Note: here we only match the name literally and do not perform namespace resolution
 * (whether `namespace Foo; var_dump()` falls back to the global function and the like is
 * handled by the reflection in Part 6). Part 1's goal is to understand the skeleton of
 * "applying a rule to the AST".
 *
 * @implements Rule<FuncCall>
 */
final class NoVarDumpRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof FuncCall);

        // A dynamic call like $callback() has no statically known name, so it is out of scope.
        if (!$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'var_dump') {
            return [];
        }

        return [new RuleError('Called var_dump().', $node->getStartLine())];
    }
}
