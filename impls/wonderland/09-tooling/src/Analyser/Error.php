<?php

declare(strict_types=1);

namespace Ministan\Analyser;

/**
 * 解析が見つけた 1 件の問題。
 *
 * PHPStan の {@see \PHPStan\Analyser\Error} に対応する最小版。
 * 以降の章でも、ルールが報告する単位はこの値オブジェクトに統一する。
 */
final readonly class Error
{
    public function __construct(
        public string $message,
        public string $file,
        public int $line,
    ) {
    }
}
