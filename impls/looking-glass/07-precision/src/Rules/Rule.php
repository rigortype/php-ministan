<?php

declare(strict_types=1);

namespace Ministan\Rules;

use Ministan\Analyser\Scope;
use PhpParser\Node;

/**
 * A checker that inspects one kind of AST node and returns the problems it finds.
 *
 * Corresponds to PHPStan's {@see \PHPStan\Rules\Rule}. `getNodeType()` decides
 * "which node to react to", and `processNode()` decides "what to report".
 *
 * In Part 2, a {@see Scope} was added to `processNode()`. With it, a rule can decide
 * based on "what is known at this node's location (which variables are defined, and from
 * Part 4 onward the type of each expression)".
 *
 * @template TNodeType of Node
 */
interface Rule
{
    /**
     * The class name of the node this rule reacts to.
     * Return an abstract type (e.g. {@see Node\Expr}) to react to all of its descendants.
     *
     * @return class-string<TNodeType>
     */
    public function getNodeType(): string;

    /**
     * @param TNodeType $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array;
}
