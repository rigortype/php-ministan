<?php

declare(strict_types=1);

namespace Ministan\Output;

use Ministan\Analyser\Error;

/**
 * 人間向けの既定フォーマット。ファイルごとにまとめて表示する。
 */
final class TableErrorFormatter implements ErrorFormatter
{
    public function format(array $errors): string
    {
        if ($errors === []) {
            return "[OK] No errors\n";
        }

        /** @var array<string, list<Error>> $byFile */
        $byFile = [];
        foreach ($errors as $error) {
            $byFile[$error->file][] = $error;
        }

        $lines = [];
        foreach ($byFile as $file => $fileErrors) {
            $lines[] = $file;
            foreach ($fileErrors as $error) {
                $lines[] = sprintf('  %4d  %s', $error->line, $error->message);
            }
            $lines[] = '';
        }

        $count = count($errors);
        $lines[] = sprintf('[ERROR] Found %d error%s', $count, $count === 1 ? '' : 's');

        return implode("\n", $lines) . "\n";
    }
}
