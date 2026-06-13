<?php

declare(strict_types=1);

namespace Ministan\Command;

use Ministan\Analyser\NodeScopeResolver;
use Ministan\Analyser\Parsing;
use Ministan\Analyser\Scope;
use Ministan\Reflection\ReflectionProvider;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use PhpParser\Error as ParserError;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * `ministan annotate <file>` の実装。
 *
 * 各代入・各 return について、推論された型を表示する。PHPStan には無いが、
 * chibirigor の `annotate` に倣ったコマンド。型推論を「目で見える」ものにし、
 * 解析器の頭の中を覗くデバッグ手段にもなる。
 *
 * 解析（ルール適用）と同じ {@see NodeScopeResolver} を、別のコールバックで再利用する。
 */
final class AnnotateCommand
{
    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        if ($args === []) {
            fwrite(STDERR, "Usage: ministan annotate <file>\n");

            return 1;
        }

        $file = $args[0];
        $code = @file_get_contents($file);
        if ($code === false) {
            fwrite(STDERR, sprintf("File \"%s\" was not found.\n", $file));

            return 1;
        }

        try {
            $ast = Parsing::parse($code);
        } catch (ParserError $e) {
            fwrite(STDERR, sprintf("%s on line %d\n", $e->getRawMessage(), $e->getStartLine()));

            return 1;
        }

        ReflectionProviderStaticAccessor::set(ReflectionProvider::fromNodes($ast));

        /** @var list<array{int, string, string}> $rows */
        $rows = [];
        $resolver = new NodeScopeResolver(
            static function (Node $node, Scope $scope) use (&$rows): void {
                if ($node instanceof Expr\Assign
                    && $node->var instanceof Expr\Variable
                    && is_string($node->var->name)
                ) {
                    // コールバックは代入の処理前に呼ばれるので、右辺は現在のスコープで推論できる。
                    $rows[] = [
                        $node->getStartLine(),
                        '$' . $node->var->name,
                        $scope->getType($node->expr)->describe(),
                    ];
                } elseif ($node instanceof Stmt\Return_ && $node->expr !== null) {
                    $rows[] = [
                        $node->getStartLine(),
                        'return',
                        $scope->getType($node->expr)->describe(),
                    ];
                }
            },
        );

        $resolver->processNodes($ast, Scope::createForFile());

        echo $this->format($file, $rows);

        return 0;
    }

    /**
     * @param list<array{int, string, string}> $rows
     */
    private function format(string $file, array $rows): string
    {
        if ($rows === []) {
            return sprintf("%s\n  (推論できる代入・return がありません)\n", $file);
        }

        $labelWidth = 0;
        foreach ($rows as [, $label]) {
            $labelWidth = max($labelWidth, strlen($label));
        }

        $out = $file . "\n";
        foreach ($rows as [$line, $label, $type]) {
            $out .= sprintf("  %4d  %-{$labelWidth}s : %s\n", $line, $label, $type);
        }

        return $out;
    }
}
