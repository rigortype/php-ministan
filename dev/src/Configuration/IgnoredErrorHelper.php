<?php

declare(strict_types=1);

namespace Ministan\Configuration;

use Ministan\Analyser\Error;

/**
 * `ignoreErrors` の正規表現にマッチした指摘を取り除く。
 *
 * baseline が「この具体的な箇所」を無視するのに対し、ignoreErrors は「この種の
 * メッセージ」をパターンで無視する。PHPStan の同名機能に対応。
 */
final readonly class IgnoredErrorHelper
{
    /**
     * @param list<string> $patterns デリミタ付き正規表現
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
