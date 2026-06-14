<?php

declare(strict_types=1);

namespace Ministan\Rules\Variables;

use Ministan\Analyser\Scope;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

/**
 * 定義されていない変数の**読み取り**を検出する。PHPStan level 0 の代表選手。
 *
 * このルールは木の走査を知らない。ただ「いまこの地点の {@see Scope} に、この変数は
 * 定義済みとして載っているか？」を問うだけ。読み取りか書き込みかの区別、isset() の
 * 内側か否か、といった文脈判断は {@see \Ministan\Analyser\NodeScopeResolver} が担い、
 * このルールには「報告すべき読み取り地点」でのみ Variable ノードが渡る。
 *
 * @implements Rule<Variable>
 */
final class UndefinedVariableRule implements Rule
{
    public function getNodeType(): string
    {
        return Variable::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof Variable);

        // 可変変数 $$name は静的に名前が定まらない → 追わない（non-rejecting）。
        if (!is_string($node->name)) {
            return [];
        }

        if ($scope->hasVariable($node->name)) {
            return [];
        }

        return [new RuleError(
            sprintf('Undefined variable: $%s', $node->name),
            $node->getStartLine(),
        )];
    }
}
