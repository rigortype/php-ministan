<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Rules\RuleRegistry;
use PhpParser\Error as ParserError;
use PhpParser\Node;
use PhpParser\ParserFactory;

/**
 * 解析パイプラインの入口。
 *
 * Part 0: 構文エラーの翻訳。
 * Part 1: パース済み AST にルール群を適用する。
 * Part 2: スコープを伝播させながらルールを適用する。
 * Part 4: スコープが各式の型を推論する。ルール適用は走査へのコールバックとして渡す。
 */
final class Analyser
{
    public function __construct(
        private readonly RuleRegistry $registry,
    ) {
    }

    /**
     * @return list<Error>
     */
    public function analyseFile(string $file): array
    {
        $code = @file_get_contents($file);
        if ($code === false) {
            return [new Error(sprintf('File "%s" was not found.', $file), $file, 0)];
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code) ?? [];
        } catch (ParserError $e) {
            // 構文エラーがある間はルールを走らせても意味がないので、ここで打ち切る。
            return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
        }

        $errors = [];
        $resolver = new NodeScopeResolver(
            function (Node $node, Scope $scope) use (&$errors, $file): void {
                foreach ($this->registry->getRulesFor($node) as $rule) {
                    foreach ($rule->processNode($node, $scope) as $ruleError) {
                        $errors[] = new Error($ruleError->message, $file, $ruleError->line);
                    }
                }
            },
        );

        $resolver->processNodes($ast, Scope::createForFile());

        return $errors;
    }
}
