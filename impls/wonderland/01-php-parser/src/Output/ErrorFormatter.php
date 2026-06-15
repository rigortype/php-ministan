<?php

declare(strict_types=1);

namespace Ministan\Output;

use Ministan\Analyser\Error;

/**
 * Formats analysis results for humans.
 *
 * Corresponds to PHPStan's {@see \PHPStan\Command\ErrorFormatter\ErrorFormatter}.
 * Extended to multiple formats such as JSON in Part 9.
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
