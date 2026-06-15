<?php

declare(strict_types=1);

namespace Ministan\Analyser;

/**
 * A single problem found by the analysis.
 *
 * The minimal counterpart of PHPStan's {@see \PHPStan\Analyser\Error}.
 * In the chapters that follow, every finding a rule reports is unified into this value object.
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
