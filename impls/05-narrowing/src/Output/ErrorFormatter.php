<?php

declare(strict_types=1);

namespace Ministan\Output;

use Ministan\Analyser\Error;

/**
 * 解析結果を人間向けに整形する。
 *
 * PHPStan の {@see \PHPStan\Command\ErrorFormatter\ErrorFormatter} に対応。
 * Part 9 で JSON など複数フォーマットに拡張する。
 */
final class ErrorFormatter
{
    /**
     * @param list<Error> $errors
     */
    public function format(array $errors): string
    {
        if ($errors === []) {
            return "[OK] No errors\n";
        }

        $lines = [];
        foreach ($errors as $error) {
            $lines[] = sprintf(' %s:%d', $error->file, $error->line);
            $lines[] = sprintf('   %s', $error->message);
        }

        $count = count($errors);
        $lines[] = '';
        $lines[] = sprintf('[ERROR] Found %d error%s', $count, $count === 1 ? '' : 's');

        return implode("\n", $lines) . "\n";
    }
}
