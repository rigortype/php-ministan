<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use PhpParser\Error as ParserError;
use PhpParser\ParserFactory;

/**
 * The entry point of the analysis pipeline.
 *
 * In Part 0 it only "validates the syntax and converts syntax errors into {@see Error}".
 * From Part 1 onward, this is where the rules are applied to the parsed AST.
 */
final class Analyser
{
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
            $parser->parse($code);
        } catch (ParserError $e) {
            // Translate php-parser's syntax errors into ministan diagnostics.
            return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
        }

        // If the syntax is valid, there is nothing to report in Part 0.
        return [];
    }
}
