<?php

declare(strict_types=1);

namespace Ministan\Analyser;

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
 */
final class Analyser
{
    public function __construct(
        private readonly RuleRegistry $registry,
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

        try {
            $ast = Parsing::parse($code);
        } catch (ParserError $e) {
            // While there are syntax errors, running the rules is pointless, so bail out here.
            return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
        }

        // Build reflection from the declarations under analysis so type objects can look it up.
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
