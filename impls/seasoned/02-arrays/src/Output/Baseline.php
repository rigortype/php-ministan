<?php

declare(strict_types=1);

namespace Ministan\Output;

use Ministan\Analyser\Error;

/**
 * 既存の指摘を「許容済み」として記録し、次回以降は無視する仕組み。
 *
 * レガシーコードに PHPStan を導入する第一歩。今あるエラーを baseline に固め、
 * 「これ以上増やさない」運用を可能にする。本実装は (ファイル, メッセージ) の組で
 * 突き合わせる簡略版（PHPStan は件数まで見る）。フォーマットは JSON。
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
     * baseline に載っている指摘を取り除く。
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
