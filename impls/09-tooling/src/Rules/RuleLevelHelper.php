<?php

declare(strict_types=1);

namespace Ministan\Rules;

use Ministan\TrinaryLogic;
use Ministan\Type\Type;

/**
 * 「期待される型に、実際の型を入れてよいか」をレベルに応じて判定する。
 *
 * PHPStan の {@see \PHPStan\Rules\RuleLevelHelper} に対応する核心。鍵は三値:
 * - Yes（確実に適合）→ 常に OK
 * - No（確実に不適合）→ 常にエラー
 * - Maybe（mixed 等で不確実）→ **高レベルでのみ**エラー
 *
 * これが「レベルを上げるほど厳しくなる」の正体。低レベルでは mixed を素通りさせ、
 * 高レベルでは mixed の混入も咎める。non-rejecting の哲学がレベル軸に乗る。
 */
final readonly class RuleLevelHelper
{
    /** この閾値以上のレベルで「Maybe」を不適合として扱う。 */
    private const int STRICT_LEVEL = 7;

    public function __construct(
        private int $level,
    ) {
    }

    public function isAcceptable(Type $accepting, Type $accepted): bool
    {
        return match ($accepting->accepts($accepted)) {
            TrinaryLogic::Yes => true,
            TrinaryLogic::No => false,
            TrinaryLogic::Maybe => $this->level < self::STRICT_LEVEL,
        };
    }
}
