<?php

declare(strict_types=1);

namespace Ministan\Output;

/**
 * 機械可読な JSON フォーマット。CI やエディタ連携で使う。
 * PHPStan の JSON 出力を簡略化した形。
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
