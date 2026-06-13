<?php

declare(strict_types=1);

namespace Ministan\Command;

use Ministan\Analyser\Analyser;
use Ministan\Output\ErrorFormatter;
use Ministan\Rules\RuleRegistryFactory;

/**
 * `ministan analyse [--level=N] <file>` の実装。
 *
 * PHPStan の AnalyseCommand に対応する最小版。Part 9 で複数ファイル・複数フォーマットや
 * baseline へ拡張する。
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
