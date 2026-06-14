<?php

declare(strict_types=1);

namespace Ministan;

/**
 * 三値論理: はい / たぶん / いいえ。
 *
 * PHPStan の {@see \PHPStan\TrinaryLogic} に対応する。静的解析では「確実にそう」
 * 「確実に違う」だけでなく「そうかもしれない」が頻出する。`mixed` は int かもしれず、
 * union 型は一部の経路でのみ条件を満たすかもしれない。この「たぶん」を一級市民として
 * 扱えることが、偽陽性を出さない（non-rejecting）解析の土台になる。
 *
 * 経験則: レベルの低い解析は「いいえ」だけを報告し、「たぶん」は見逃す。レベルを
 * 上げるほど「たぶん」も問題として扱う。Part 8 のレベル制はこの軸に乗る。
 */
enum TrinaryLogic
{
    case Yes;
    case Maybe;
    case No;

    public function yes(): bool
    {
        return $this === self::Yes;
    }

    public function maybe(): bool
    {
        return $this === self::Maybe;
    }

    public function no(): bool
    {
        return $this === self::No;
    }

    public function negate(): self
    {
        return match ($this) {
            self::Yes => self::No,
            self::No => self::Yes,
            self::Maybe => self::Maybe,
        };
    }

    public function and(self $other): self
    {
        return match (true) {
            $this === self::No || $other === self::No => self::No,
            $this === self::Maybe || $other === self::Maybe => self::Maybe,
            default => self::Yes,
        };
    }

    public function or(self $other): self
    {
        return match (true) {
            $this === self::Yes || $other === self::Yes => self::Yes,
            $this === self::Maybe || $other === self::Maybe => self::Maybe,
            default => self::No,
        };
    }

    /**
     * 全 operand が一致すればその値、1 つでも異なれば Maybe。
     *
     * union との関係に使う —— ある型が union の「全メンバに対して Yes」なら Yes、
     * 「全メンバに対して No」なら No、混在すれば部分一致なので Maybe。`and`（min）で
     * 畳むと部分一致が No に潰れ、union の一部不適合を低レベルで誤検出してしまう。
     * PHPStan の {@see \PHPStan\TrinaryLogic::extremeIdentity} に対応。
     *
     * @param non-empty-list<self> $operands
     */
    public static function extremeIdentity(array $operands): self
    {
        $first = $operands[0];
        foreach ($operands as $operand) {
            if ($operand !== $first) {
                return self::Maybe;
            }
        }

        return $first;
    }

    public function describe(): string
    {
        return match ($this) {
            self::Yes => 'yes',
            self::Maybe => 'maybe',
            self::No => 'no',
        };
    }
}
