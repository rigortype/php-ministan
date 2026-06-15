<?php

declare(strict_types=1);

namespace Ministan\Configuration;

/**
 * The analysis configuration. An immutable object assembled from the NEON file and the CLI.
 *
 * @phpstan-type RuleClass class-string<\Ministan\Rules\Rule<\PhpParser\Node>>
 */
final readonly class Configuration
{
    /**
     * @param list<string> $paths        the paths to analyse
     * @param list<string> $ignoreErrors regular expressions; matching messages are ignored
     * @param list<string> $ruleClasses  class names of additional rules to register
     */
    public function __construct(
        public int $level,
        public array $paths,
        public array $ignoreErrors,
        public array $ruleClasses,
    ) {
    }
}
