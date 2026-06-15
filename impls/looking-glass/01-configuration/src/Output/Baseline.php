<?php

declare(strict_types=1);

namespace Ministan\Output;

use Ministan\Analyser\Error;

/**
 * A mechanism that records existing findings as "accepted" and ignores them from then on.
 *
 * The first step in introducing PHPStan to legacy code. Freeze today's errors into a
 * baseline so you can run a "don't add any more" policy. This implementation is a
 * simplified version that matches on the (file, message) pair (PHPStan also looks at the
 * count). The format is JSON.
 */
final class Baseline
{
    /**
     * @param list<Error> $errors
     */
    public static function generate(array $errors): string
    {
        $entries = array_map(
            static fn (Error $error): array => ['message' => $error->message, 'file' => $error->file],
            $errors,
        );

        return json_encode(['errors' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Removes findings that are listed in the baseline.
     *
     * @param list<Error> $errors
     *
     * @return list<Error>
     */
    public static function filter(array $errors, string $baselineJson): array
    {
        $decoded = json_decode($baselineJson, true);
        $ignored = [];
        foreach ($decoded['errors'] ?? [] as $entry) {
            $ignored[self::key($entry['file'] ?? '', $entry['message'] ?? '')] = true;
        }

        return array_values(array_filter(
            $errors,
            static fn (Error $error): bool => !isset($ignored[self::key($error->file, $error->message)]),
        ));
    }

    private static function key(string $file, string $message): string
    {
        return $file . "\0" . $message;
    }
}
