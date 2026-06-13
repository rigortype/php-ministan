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
     * @param list<Type> $parameterTypes
     * @param array<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     *
     * @return list<array{int, Type, Type}> [位置(1始まり), 期待型, 実際の型] の不一致
     */
    public function check(array $parameterTypes, array $args, Scope $scope): array
    {
        $mismatches = [];

        foreach ($args as $position => $arg) {
            if (!$arg instanceof Arg || $arg->unpack || $arg->name !== null) {
                continue; // ...$spread・名前付き引数は位置対応が崩れるので見送り
            }
            if (!isset($parameterTypes[$position])) {
                continue; // 余剰引数・可変長は見送り（non-rejecting）
            }

            $expected = $parameterTypes[$position];
            $actual = $scope->getType($arg->value);

            if (!$this->ruleLevelHelper->isAcceptable($expected, $actual)) {
                $mismatches[] = [$position + 1, $expected, $actual];
            }
        }

        return $mismatches;
    }
}
