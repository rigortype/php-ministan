<?php

declare(strict_types=1);

namespace Ministan\Command;

use Ministan\Analyser\Analyser;
use Ministan\Output\ErrorFormatter;
use Ministan\Rules\RuleRegistryFactory;

/**
 * `ministan analyse <file>` の実装。
 *
 * PHPStan の AnalyseCommand に対応する最小版。Part 9 で設定読み込みや
 * 複数ファイル・複数フォーマットへ拡張する。
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
