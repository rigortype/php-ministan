<?php

declare(strict_types=1);

namespace Ministan\Command;

use Ministan\Analyser\Analyser;
use Ministan\Output\ErrorFormatter;
use Ministan\Rules\RuleRegistryFactory;

/**
 * Implementation of `ministan analyse <file>`.
 *
 * A minimal counterpart to PHPStan's AnalyseCommand. Part 9 extends it with
 * configuration loading and support for multiple files and formats.
 */
final class AnalyseCommand
{
    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        if ($args === []) {
            fwrite(STDERR, "Usage: ministan analyse <file>\n");

            return 1;
        }

        $registry = (new RuleRegistryFactory())->create();
        $errors = (new Analyser($registry))->analyseFile($args[0]);

        echo (new ErrorFormatter())->format($errors);

        return $errors === [] ? 0 : 1;
    }
}
