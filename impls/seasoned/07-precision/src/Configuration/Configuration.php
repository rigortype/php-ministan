<?php

declare(strict_types=1);

namespace Ministan\Configuration;

/**
 * 解析の設定。NEON ファイルと CLI から組み立てられる不変オブジェクト。
 *
 * @phpstan-type RuleClass class-string<\Ministan\Rules\Rule<\PhpParser\Node>>
 */
final readonly class Configuration
{
    /**
     * @param list<string> $paths        解析対象のパス
     * @param list<string> $ignoreErrors メッセージにマッチしたら無視する正規表現
     * @param list<string> $ruleClasses  追加で登録するルールのクラス名
     */
    public function __construct(
        public int $level,
        public array $paths,
        public array $ignoreErrors,
        public array $ruleClasses,
    ) {
    }
}
