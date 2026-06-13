<?php

declare(strict_types=1);

namespace Ministan\Command;

use Ministan\Analyser\Analyser;
use Ministan\Analyser\FileFinder;
use Ministan\Output\Baseline;
use Ministan\Output\ErrorFormatter;
use Ministan\Output\JsonErrorFormatter;
use Ministan\Output\TableErrorFormatter;
use Ministan\Rules\RuleRegistryFactory;

/**
 * `ministan analyse [options] <paths...>` の実装。
 *
 * オプション:
 *   --level=N              ルールレベル（0..9, 既定 5）
 *   --error-format=json    JSON で出力する（既定は表）
 *   --baseline=FILE        FILE に載った既知の指摘を無視する
 *   --generate-baseline[=FILE]  現在の指摘を baseline として書き出す
 */
final class AnalyseCommand
{
    private const string DEFAULT_BASELINE = 'ministan-baseline.json';

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $level = RuleRegistryFactory::DEFAULT_LEVEL;
        $format = 'table';
        $baselineToApply = null;
        $baselineToGenerate = null;
        $paths = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--level=')) {
                $level = min((int) substr($arg, strlen('--level=')), RuleRegistryFactory::MAX_LEVEL);
            } elseif (str_starts_with($arg, '--error-format=')) {
                $format = substr($arg, strlen('--error-format='));
            } elseif (str_starts_with($arg, '--baseline=')) {
                $baselineToApply = substr($arg, strlen('--baseline='));
            } elseif ($arg === '--generate-baseline') {
                $baselineToGenerate = self::DEFAULT_BASELINE;
            } elseif (str_starts_with($arg, '--generate-baseline=')) {
                $baselineToGenerate = substr($arg, strlen('--generate-baseline='));
            } else {
                $paths[] = $arg;
            }
        }

        if ($paths === []) {
            fwrite(STDERR, "Usage: ministan analyse [--level=N] [--error-format=json] <paths...>\n");

            return 1;
        }

        $files = (new FileFinder())->find($paths);
        $registry = (new RuleRegistryFactory())->createForLevel($level);
        $errors = (new Analyser($registry))->analyse($files);

        if ($baselineToGenerate !== null) {
            file_put_contents($baselineToGenerate, Baseline::generate($errors));
            fwrite(STDERR, sprintf("Baseline written to %s (%d errors).\n", $baselineToGenerate, count($errors)));

            return 0;
        }

        if ($baselineToApply !== null && is_file($baselineToApply)) {
            $errors = Baseline::filter($errors, (string) file_get_contents($baselineToApply));
        }

        echo $this->formatter($format)->format($errors);

        return $errors === [] ? 0 : 1;
    }

    private function formatter(string $format): ErrorFormatter
    {
        return $format === 'json' ? new JsonErrorFormatter() : new TableErrorFormatter();
    }
}
