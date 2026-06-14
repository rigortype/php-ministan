<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Cache\ResultCache;
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
 * S6:     ファイル内容が変わっていなければ結果キャッシュを使う。
 */
final class Analyser
{
    public function __construct(
        private readonly RuleRegistry $registry,
        private readonly ?ResultCache $cache = null,
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

        if ($this->cache !== null) {
            $cached = $this->cache->load($code);
            if ($cached !== null) {
                // キャッシュは (メッセージ, 行) だけ持つ。ファイル名は今のものを付け直す。
                return array_map(
                    static fn (array $entry): Error => new Error($entry['message'], $file, $entry['line']),
                    $cached,
                );
            }
        }

        $errors = $this->computeErrors($code, $file);

        $this->cache?->save(
            $code,
            array_map(static fn (Error $e): array => ['message' => $e->message, 'line' => $e->line], $errors),
        );

        return $errors;
    }

    /**
     * @return list<Error>
     */
    private function computeErrors(string $code, string $file): array
    {
        try {
            $ast = Parsing::parse($code);
        } catch (ParserError $e) {
            return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
        }

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
