<?php

declare(strict_types=1);

namespace Ministan\Rules;

use Ministan\Rules\Functions\NoVarDumpRule;
use Ministan\Rules\Variables\UndefinedVariableRule;

/**
 * 既定のルール一式を組み立てる。
 *
 * PHPStan では DI コンテナと neon 設定がこの役目を担うが、ここでは素朴な
 * ファクトリで十分。Part 8 でレベル別のルール束へ発展させる。
 */
final class RuleRegistryFactory
{
    public function create(): RuleRegistry
    {
        return new RuleRegistry([
            new NoVarDumpRule(),
            new UndefinedVariableRule(),
        ]);
    }
}
