<?php

declare(strict_types=1);

namespace Ministan\Rules;

use Ministan\Rules\Functions\NoVarDumpRule;

/**
 * Assembles the default set of rules.
 *
 * In PHPStan, the DI container and neon configuration take on this role, but here a plain
 * factory is enough. It grows into level-based rule bundles in Part 8.
 */
final class RuleRegistryFactory
{
    public function create(): RuleRegistry
    {
        return new RuleRegistry([
            new NoVarDumpRule(),
        ]);
    }
}
