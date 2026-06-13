<?php

declare(strict_types=1);

namespace Ministan\Rules;

/**
 * ルールが報告する 1 件の問題。
 *
 * ファイル名はルールの関知するところではない（どのファイルを解析中かは
 * 解析器が知っている）ので、ここではメッセージと行番号だけを持つ。
 * 解析器がこれを {@see \Ministan\Analyser\Error} に昇格させる。
 */
final readonly class RuleError
{
    public function __construct(
        public string $message,
        public int $line,
    ) {
    }
}
