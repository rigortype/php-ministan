<?php

declare(strict_types=1);

namespace Ministan\Analyser;

/**
 * The pair of scopes that hold when a given condition is true vs. false.
 *
 * For `if ($x instanceof Foo)`, the truthy scope has $x: Foo while the falsy scope
 * keeps it unchanged. This captures how a condition narrows types on each side of
 * the branch. Corresponds to PHPStan's `SpecifiedTypes`.
 */
final readonly class SpecifiedTypes
{
    public function __construct(
        public Scope $truthy,
        public Scope $falsy,
    ) {
    }

    /** Negation (`!`) simply swaps the truthy and falsy scopes. */
    public function negate(): self
    {
        return new self($this->falsy, $this->truthy);
    }
}
