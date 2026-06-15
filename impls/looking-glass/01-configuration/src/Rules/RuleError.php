<?php

declare(strict_types=1);

namespace Ministan\Rules;

/**
 * A single problem reported by a rule.
 *
 * The file name is none of a rule's concern (the analyser knows which file is being
 * analysed), so this holds only the message and the line number. The analyser
 * promotes it to a {@see \Ministan\Analyser\Error}.
 */
final readonly class RuleError
{
    public function __construct(
        public string $message,
        public int $line,
    ) {
    }
}
