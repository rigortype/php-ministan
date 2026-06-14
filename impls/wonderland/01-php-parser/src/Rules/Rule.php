<?php

declare(strict_types=1);

namespace Ministan\Rules;

use PhpParser\Node;

/**
 * 1 種類の AST ノードを検査し、見つけた問題を返す検査器。
 *
 * PHPStan の {@see \PHPStan\Rules\Rule} に対応する。`getNodeType()` が
 * 「どのノードに反応するか」を、`processNode()` が「何を報告するか」を決める。
 *
 * @template TNodeType of Node
 */
interface Rule
{
    /**
     * このルールが反応するノードのクラス名。
     * 抽象型（例: {@see Node\Expr}）を返せば、その派生すべてに反応する。
     *
     * @return class-string<TNodeType>
     */
    public function getNodeType(): string;

    /**
     * @param TNodeType $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node): array;
}
