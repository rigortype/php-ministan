<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Rules\RuleRegistry;
use PhpParser\Error as ParserError;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * The entry point of the analysis pipeline.
 *
 * Part 0: translate syntax errors.
 * Part 1: apply the rules to a parsed AST (<- we are here).
 */
final class Analyser
{
    public function __construct(
        private readonly RuleRegistry $registry,
    ) {
    }

    /**
     * @return list<Error>
     */
    public function analyseFile(string $file): array
    {
        $code = @file_get_contents($file);
        if ($code === false) {
            return [new Error(sprintf('File "%s" was not found.', $file), $file, 0)];
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code) ?? [];
        } catch (ParserError $e) {
            // While there are syntax errors, running the rules is pointless, so bail out here.
            return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
        }

        $visitor = new RuleApplyingVisitor($this->registry, $file);
        (new NodeTraverser($visitor))->traverse($ast);

        return $visitor->getErrors();
    }
}
