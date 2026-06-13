<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use PhpParser\Error as ParserError;
use PhpParser\ParserFactory;

/**
 * 解析パイプラインの入口。
 *
 * Part 0 では「構文を検証し、構文エラーを {@see Error} に変換する」だけ。
 * Part 1 以降、ここでパース済み AST にルール群を適用していく。
 */
final class Analyser
{
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
            $parser->parse($code);
        } catch (ParserError $e) {
            // php-parser の構文エラーを ministan の診断に翻訳する。
            return [new Error($e->getRawMessage(), $file, $e->getStartLine())];
        }

        // 構文が通れば、Part 0 では報告すべき問題はない。
        return [];
    }
}
