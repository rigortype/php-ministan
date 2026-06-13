<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Reflection\ReflectionProvider;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\Rules\RuleRegistry;
use PhpParser\Error as ParserError;
use PhpParser\Node;

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
     * 複数ファイルを解析し、エラーをまとめて返す。
     *
     * @param list<string> $files
     *
     * @return list<Error>
     */
    public function analyse(array $files): array
    {
        $errors = [];
        foreach ($files as $file) {
            foreach ($this->analyseFile($file) as $error) {
                $errors[] = $error;
            }
        }

        return $errors;
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

        try {
            $ast = Parsing::parse($code);
        } catch (ParserError $e) {
            // 構文エラーがある間はルールを走らせても意味がないので、ここで打ち切る。
            return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
        }

        // 解析対象の宣言からリフレクションを組み、型オブジェクトから引けるようにする。
        ReflectionProviderStaticAccessor::set(ReflectionProvider::fromNodes($ast));

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
