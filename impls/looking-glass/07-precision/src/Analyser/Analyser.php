<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Cache\ResultCache;
use Ministan\Reflection\ReflectionProvider;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\Rules\RuleRegistry;
use PhpParser\Error as ParserError;
use PhpParser\Node;

/**
 * The entry point of the analysis pipeline.
 *
 * Part 0: translate syntax errors.
 * Part 1: apply the rules to a parsed AST.
 * Part 2: apply the rules while propagating scope.
 * Part 4: the scope infers the type of each expression. Rule application is passed as a callback to the traversal.
 * S6:     reuse the result cache when the file contents have not changed.
 */
final class Analyser
{
    public function __construct(
        private readonly RuleRegistry $registry,
        private readonly ?ResultCache $cache = null,
    ) {
    }

    /**
     * Analyse multiple files and return all errors together.
     *
     * @param list<string> $files
     *
     * @return list<Error>
     */
    public function analyse(array $files): array
    {
        $errors = [];
        foreach ($files as $file) {
            foreach ($this->analyseFile($file) as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
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

        if ($this->cache !== null) {
            $cached = $this->cache->load($code);
            if ($cached !== null) {
                // The cache holds only (message, line). Reattach the current file name.
                return array_map(
                    static fn (array $entry): Error => new Error($entry['message'], $file, $entry['line']),
                    $cached,
                );
            }
        }

        $errors = $this->computeErrors($code, $file);

        $this->cache?->save(
            $code,
            array_map(static fn (Error $e): array => ['message' => $e->message, 'line' => $e->line], $errors),
        );

        return $errors;
    }

    /**
     * @return list<Error>
     */
    private function computeErrors(string $code, string $file): array
    {
        try {
            $ast = Parsing::parse($code);
        } catch (ParserError $e) {
            return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
        }

        ReflectionProviderStaticAccessor::set(ReflectionProvider::fromNodes($ast));

        $errors = [];
        $resolver = new NodeScopeResolver(
            function (Node $node, Scope $scope) use (&$errors, $file): void {
                foreach ($this->registry->getRulesFor($node) as $rule) {
                    foreach ($rule->processNode($node, $scope) as $ruleError) {
                        $errors[] = new Error($ruleError->message, $file, $ruleError->line);
                    }
                }
            },
        );

        $resolver->processNodes($ast, Scope::createForFile());

        return $errors;
    }
}
