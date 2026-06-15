<?php

declare(strict_types=1);

namespace Ministan;

/**
 * Three-valued logic: Yes / Maybe / No.
 *
 * Corresponds to PHPStan's {@see \PHPStan\TrinaryLogic}. In static analysis, not only "definitely
 * so" and "definitely not", but also "maybe so" comes up constantly. `mixed` might be an int, and
 * a union type might satisfy a condition only on some paths. Being able to treat this "maybe" as a
 * first-class citizen is the foundation of analysis that produces no false positives (non-rejecting).
 *
 * Rule of thumb: low-level analysis reports only "No" and overlooks "Maybe". The higher the level,
 * the more "Maybe" is also treated as a problem. The level system in Part 8 rides on this axis.
 */
enum TrinaryLogic
{
    case Yes;
    case Maybe;
    case No;

    public function yes(): bool
    {
        return $this === self::Yes;
    }

    public function maybe(): bool
    {
        return $this === self::Maybe;
    }

    public function no(): bool
    {
        return $this === self::No;
    }

    public function negate(): self
    {
        return match ($this) {
            self::Yes => self::No,
            self::No => self::Yes,
            self::Maybe => self::Maybe,
        };
    }

    public function and(self $other): self
    {
        return match (true) {
            $this === self::No || $other === self::No => self::No,
            $this === self::Maybe || $other === self::Maybe => self::Maybe,
            default => self::Yes,
        };
    }

    public function or(self $other): self
    {
        return match (true) {
            $this === self::Yes || $other === self::Yes => self::Yes,
            $this === self::Maybe || $other === self::Maybe => self::Maybe,
            default => self::No,
        };
    }

    public function describe(): string
    {
        return match ($this) {
            self::Yes => 'yes',
            self::Maybe => 'maybe',
            self::No => 'no',
        };
    }
}
