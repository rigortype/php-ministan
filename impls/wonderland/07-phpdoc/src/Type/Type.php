<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * The algebraic object that represents a type. The core corresponding to PHPStan's {@see \PHPStan\Type\Type}.
 *
 * Three basic operations ask about the relation between types:
 *
 * - {@see describe()} … a human-facing string (`int`, `'foo'`, `mixed` …)
 * - {@see isSuperTypeOf()} … the subtype relation. "Are all of $type's values also my values?"
 * - {@see accepts()} … assignability. "May I put a value of $type into my slot?"
 *
 * Every answer is a {@see TrinaryLogic}. `int` accepts `mixed` with "maybe", and
 * `string` never accepts `int` (no). That "maybe" is the key to the level system.
 */
interface Type
{
    public function describe(): string;

    public function accepts(Type $type): TrinaryLogic;

    public function isSuperTypeOf(Type $type): TrinaryLogic;

    public function equals(Type $type): bool;
}
