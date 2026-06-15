<?php

declare(strict_types=1);

namespace Ministan\Output;

/**
 * A machine-readable JSON format, used for CI and editor integration.
 * A simplified form of PHPStan's JSON output.
 */
final class JsonErrorFormatter implements ErrorFormatter
{
    public function format(array $errors): string
    {
        $files = [];
        foreach ($errors as $error) {
            $files[$error->file]['errors'] ??= 0;
            $files[$error->file]['errors']++;
            $files[$error->file]['messages'][] = [
                'message' => $error->message,
                'line' => $error->line,
            ];
        }

        $payload = [
            'totals' => ['file_errors' => count($errors)],
            'files' => $files,
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
}
