<?php

declare(strict_types=1);

namespace Ministan\Rules\Functions;

use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

/**
 * デバッグ用の `var_dump()` 呼び出しの消し忘れを検出する、最初の「本物の」ルール。
 *
 * 型を一切使わない、純粋な構文パターンマッチ。これだけでも実用的な lint になる。
 *
 * 注: ここでは名前を字面で照合するだけで、名前空間解決はしない
 * （`namespace Foo; var_dump()` がグローバルにフォールバックするか等は Part 6 の
 * リフレクションで扱う）。Part 1 は「AST にルールを当てる」骨組みの理解が目的。
 *
 * @implements Rule<FuncCall>
 */
final class NoVarDumpRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node): array
    {
        assert($node instanceof FuncCall);

        // $callback() のような動的呼び出しは名前が静的に分からないので対象外。
        if (!$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'var_dump') {
            return [];
        }

        return [new RuleError('Called var_dump().', $node->getStartLine())];
    }
}
