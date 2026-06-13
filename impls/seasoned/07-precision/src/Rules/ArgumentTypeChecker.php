<?php

declare(strict_types=1);

namespace Ministan\Rules;

use Ministan\Analyser\Scope;
use Ministan\Type\Type;
use PhpParser\Node\Arg;

/**
 * 呼び出しの実引数を、宣言されたパラメータ型と照合する共通処理。
 * 関数呼び出しとメソッド呼び出しのルールが共有する。
 */
final readonly class ArgumentTypeChecker
{
    public function __construct(
        private RuleLevelHelper $ruleLevelHelper,
    ) {
    }

    /**
     * @param list<Type>   $parameterTypes
     * @param list<string> $parameterNames 名前付き引数を位置へ対応づけるための名前
     * @param array<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     *
     * @return list<array{int, Type, Type}> [位置(1始まり), 期待型, 実際の型] の不一致
     */
    public function check(array $parameterTypes, array $parameterNames, array $args, Scope $scope): array
    {
        $mismatches = [];

        foreach ($args as $position => $arg) {
            if (!$arg instanceof Arg || $arg->unpack) {
                continue; // ...$spread は位置対応が崩れるので見送り
            }

            // 名前付き引数は、宣言の何番目かを名前から解決する。
            $index = $arg->name !== null
                ? array_search($arg->name->toString(), $parameterNames, true)
                : $position;
            if ($index === false || !isset($parameterTypes[$index])) {
                continue;
            }

            $expected = $parameterTypes[$index];
            $actual = $scope->getType($arg->value);

            if (!$this->ruleLevelHelper->isAcceptable($expected, $actual)) {
                $mismatches[] = [$index + 1, $expected, $actual];
            }
        }

        return $mismatches;
    }
}
