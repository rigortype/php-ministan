<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * 単純な値型（int, string, null …）が共有する定型処理。
 *
 * - `accepts` は `isSuperTypeOf` に委譲する（これらの型では代入可能性＝部分型関係）。
 *   float が int を受け入れる、といった暗黙変換は後の章の精密化に回す。
 * - `equals` は同じクラスかどうか。定数型は値も比べるので個別に上書きする。
 * - `relateToTopAndBottom` は、どの型にも共通の「上端 mixed・下端 never」との関係を返す。
 */
trait SimpleTypeTrait
{
    public function accepts(Type $type): TrinaryLogic
    {
        return $this->isSuperTypeOf($type);
    }

    public function equals(Type $type): bool
    {
        return $type::class === static::class;
    }

    /**
     * never はあらゆる型の部分型（= 常に Yes）。mixed はどの型でもありうる（= Maybe）。
     * どちらでもなければ null を返し、呼び出し側の固有判定に委ねる。
     */
    protected static function relateToTopAndBottom(Type $type): ?TrinaryLogic
    {
        if ($type instanceof NeverType) {
            return TrinaryLogic::Yes;
        }

        if ($type instanceof MixedType) {
            return TrinaryLogic::Maybe;
        }

        return null;
    }
}
