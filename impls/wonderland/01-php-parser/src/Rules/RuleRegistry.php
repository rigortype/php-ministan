<?php

declare(strict_types=1);

namespace Ministan\Rules;

use PhpParser\Node;

/**
 * Indexes rules by node kind and returns the rules that should react to a given node.
 *
 * Corresponds to PHPStan's {@see \PHPStan\Rules\Registry}. The index is built from the return
 * value of `getNodeType()`, but on lookup it also walks the node's **class hierarchy** (parent
 * classes and implemented interfaces). This lets a rule that targets the concrete
 * {@see Node\Expr\FuncCall} and a rule that targets the abstract {@see Node\Expr} coexist
 * through the same mechanism.
 */
final class RuleRegistry
{
    /** @var array<class-string<Node>, list<Rule<Node>>> */
    private array $rules = [];

    /**
     * @param iterable<Rule<Node>> $rules
     */
    public function __construct(iterable $rules)
    {
        foreach ($rules as $rule) {
            $this->rules[$rule->getNodeType()][] = $rule;
        }
    }

    /**
     * @return list<Rule<Node>>
     */
    public function getRulesFor(Node $node): array
    {
        $matched = [];
        foreach ($this->classHierarchy($node) as $class) {
            foreach ($this->rules[$class] ?? [] as $rule) {
                $matched[] = $rule;
            }
        }

        return $matched;
    }

    /**
     * Enumerates from the node's own class through its parent classes and implemented interfaces.
     *
     * @return list<class-string>
     */
    private function classHierarchy(Node $node): array
    {
        $class = $node::class;

        return [
            $class,
            ...array_values(class_parents($class)),
            ...array_values(class_implements($class)),
        ];
    }
}
