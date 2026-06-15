<?php

declare(strict_types=1);

namespace Ministan\Rules;

use Ministan\Rules\Functions\FunctionCallParameterTypesRule;
use Ministan\Rules\Functions\FunctionReturnTypeRule;
use Ministan\Rules\Functions\NoVarDumpRule;
use Ministan\Rules\Methods\CallToUndefinedMethodRule;
use Ministan\Rules\Methods\MethodCallParameterTypesRule;
use Ministan\Rules\Variables\UndefinedVariableRule;

/**
 * Assembles the bundle of rules for a given level.
 *
 * In PHPStan, neon and DI take on this role, but here we simply keep a "table that pairs each
 * rule with a minimum level" and collect those at or below the requested level. The higher the
 * level, the more rules there are, and {@see RuleLevelHelper} also makes the type matching itself
 * stricter -- strictness grows in two stages.
 */
final class RuleRegistryFactory
{
    public const int MAX_LEVEL = 9;

    public const int DEFAULT_LEVEL = 5;

    public function create(): RuleRegistry
    {
        return $this->createForLevel(self::MAX_LEVEL);
    }

    /**
     * @param list<Rule<\PhpParser\Node>> $extraRules custom rules added via configuration
     */
    public function createForLevel(int $level, array $extraRules = []): RuleRegistry
    {
        $helper = new RuleLevelHelper($level);
        $checker = new ArgumentTypeChecker($helper);

        /** @var list<array{int, Rule<\PhpParser\Node>}> $leveled */
        $leveled = [
            [0, new NoVarDumpRule()],
            [0, new CallToUndefinedMethodRule()],
            [0, new UndefinedVariableRule()],
            [5, new FunctionCallParameterTypesRule($checker)],
            [5, new MethodCallParameterTypesRule($checker)],
            [6, new FunctionReturnTypeRule($helper)],
        ];

        $active = [];
        foreach ($leveled as [$ruleLevel, $rule]) {
            if ($ruleLevel <= $level) {
                $active[] = $rule;
            }
        }

        foreach ($extraRules as $rule) {
            $active[] = $rule;
        }

        return new RuleRegistry($active);
    }
}
