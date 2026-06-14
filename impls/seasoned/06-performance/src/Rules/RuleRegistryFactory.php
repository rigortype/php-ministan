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
 * レベルに応じたルール束を組み立てる。
 *
 * PHPStan では neon と DI がこの役目を担うが、ここでは素朴に「各ルールに最低レベルを
 * 添えた表」を持ち、要求レベル以下のものを集める。レベルを上げるほどルールが増え、
 * かつ {@see RuleLevelHelper} が型照合自体も厳しくする——二段構えで厳しさが増す。
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
     * @param list<Rule<\PhpParser\Node>> $extraRules 設定で追加されたカスタムルール
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
