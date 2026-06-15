<?php

declare(strict_types=1);

namespace Ministan\Analyser;

/**
 * An immutable object representing "what is known at a given point".
 *
 * It corresponds to PHPStan's {@see \PHPStan\Analyser\Scope}. In Part 2 it holds
 * only "which variables are defined". In Parts 3-4 a variable -> **type** mapping
 * is layered on top of it.
 *
 * Immutability is essential. As we walk down the tree, `assignVariable()` produces
 * a **new** Scope to pass along, so each branch carries its own set of facts and can
 * be merged back safely later.
 */
final readonly class Scope
{
    /**
     * @param array<string, true> $variables the set of defined variable names
     */
    private function __construct(
        private array $variables,
    ) {
    }

    /**
     * The file-level (global) scope. Superglobals are always defined.
     */
    public static function createForFile(): self
    {
        return self::createForFunction();
    }

    /**
     * A new scope at a function boundary. Local variables from the outside are not
     * visible, but superglobals can still be referenced inside the function.
     */
    public static function createForFunction(): self
    {
        $superglobals = [
            'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES',
            '_COOKIE', '_SESSION', '_REQUEST', '_ENV',
        ];

        $variables = [];
        foreach ($superglobals as $name) {
            $variables[$name] = true;
        }

        return new self($variables);
    }

    public function hasVariable(string $name): bool
    {
        return isset($this->variables[$name]);
    }

    public function assignVariable(string $name): self
    {
        if (isset($this->variables[$name])) {
            return $this;
        }

        return new self([...$this->variables, $name => true]);
    }

    /**
     * Merges two scopes.
     *
     * In Part 2 this is an optimistic union: "defined in either branch means defined".
     * This keeps it from producing **false positives** across conditional branches
     * (non-rejecting). PHPStan instead treats a variable as certain only when it is
     * defined on every path, and otherwise reports "may be undefined" separately --
     * that refinement is covered in Part 5.
     */
    public function mergeWith(self $other): self
    {
        return new self($this->variables + $other->variables);
    }
}
