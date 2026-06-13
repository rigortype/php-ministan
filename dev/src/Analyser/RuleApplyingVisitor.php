<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Rules\RuleRegistry;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * AST を 1 ノードずつ歩き、各ノードに反応するルールを適用して {@see Error} を集める。
 *
 * PHPStan ではスコープを伝播させる NodeScopeResolver がこの役目を担うが、
 * Part 1 ではまだ型もスコープもない。「ノードを訪れてルールを当てる」核だけを置く。
 * Part 2 でここに {@see Scope} の伝播が加わる。
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
