<?php

declare(strict_types=1);

namespace Ministan\Command;

use Ministan\Analyser\NodeScopeResolver;
use Ministan\Analyser\Parsing;
use Ministan\Analyser\Scope;
use Ministan\Reflection\PhpDocTypeResolver;
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
        $phpDoc = new PhpDocTypeResolver();
        $resolver = new NodeScopeResolver(
            static function (Node $node, Scope $scope) use (&$rows, $phpDoc): void {
                if ($node instanceof Stmt\Expression
                    && $node->expr instanceof Expr\Assign
                    && $node->expr->var instanceof Expr\Variable
                    && is_string($node->expr->var->name)
                ) {
                    $name = $node->expr->var->name;

                    // @var があればそれを、無ければ右辺の推論型を表示する。
                    $type = null;
                    $doc = $node->getDocComment();
                    if ($doc !== null) {
                        $parsed = $phpDoc->parse($doc->getText());
                        $type = $parsed->varTypes[$name] ?? $parsed->varTypes[''] ?? null;
                    }
                    $type ??= $scope->getType($node->expr->expr);

                    $rows[] = [$node->getStartLine(), '$' . $name, $type->describe()];
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
