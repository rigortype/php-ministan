<?php

declare(strict_types=1);

namespace Ministan\Command;

use Ministan\Analyser\Analyser;
use Ministan\Output\ErrorFormatter;
use Ministan\Rules\RuleRegistryFactory;

/**
 * Implementation of `ministan analyse [--level=N] <file>`.
 *
 * A minimal counterpart to PHPStan's AnalyseCommand. Part 9 extends it with
 * support for multiple files and formats and the baseline.
 */
final class AnalyseCommand
{
    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $level = RuleRegistryFactory::DEFAULT_LEVEL;
        $files = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--level=')) {
                $level = min((int) substr($arg, strlen('--level=')), RuleRegistryFactory::MAX_LEVEL);
            } else {
                $files[] = $arg;
            }
        }

        if ($files === []) {
            fwrite(STDERR, "Usage: ministan analyse [--level=N] <file>\n");

            return 1;
        }

        $registry = (new RuleRegistryFactory())->createForLevel($level);
        $errors = (new Analyser($registry))->analyseFile($files[0]);

        echo (new ErrorFormatter())->format($errors);

        return $errors === [] ? 0 : 1;
    }
}
