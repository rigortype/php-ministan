<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Rules\RuleRegistry;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Walks the AST one node at a time, applies the rules that react to each node, and
 * collects {@see Error}s.
 *
 * In PHPStan this role is played by NodeScopeResolver, which also propagates scope; in
 * Part 1 we have neither types nor scope yet, so this holds just the core: "visit a node,
 * apply the rules." Part 2 adds {@see Scope} propagation here.
 */
final class RuleApplyingVisitor extends NodeVisitorAbstract
{
    /** @var list<Error> */
    private array $errors = [];

    public function __construct(
        private readonly RuleRegistry $registry,
        private readonly string $file,
    ) {
    }

    public function enterNode(Node $node): null
    {
        foreach ($this->registry->getRulesFor($node) as $rule) {
            foreach ($rule->processNode($node) as $ruleError) {
                $this->errors[] = new Error($ruleError->message, $this->file, $ruleError->line);
            }
        }

        return null;
    }

    /**
     * @return list<Error>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
