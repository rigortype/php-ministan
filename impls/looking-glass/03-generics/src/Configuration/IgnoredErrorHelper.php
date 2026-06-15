<?php

declare(strict_types=1);

namespace Ministan\Configuration;

use Ministan\Analyser\Error;

/**
 * Removes findings that match an `ignoreErrors` regular expression.
 *
 * Where a baseline ignores "this specific spot", ignoreErrors ignores "this kind of
 * message" by pattern. Corresponds to PHPStan's feature of the same name.
 */
final readonly class IgnoredErrorHelper
{
    /**
     * @param list<string> $patterns regular expressions with delimiters
     */
    public function __construct(
        private array $patterns,
    ) {
    }

    /**
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public function filter(array $errors): array
    {
        if ($this->patterns === []) {
            return $errors;
        }

        return array_values(array_filter(
            $errors,
            fn (Error $error): bool => !$this->isIgnored($error->message),
        ));
    }

    private function isIgnored(string $message): bool
    {
        foreach ($this->patterns as $pattern) {
            if (@preg_match($pattern, $message) === 1) {
                return true;
            }
        }

        return false;
    }
}
