<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * 単純な値型（int, string, null …）が共有する定型処理。
 *
 * - `accepts` は `isSuperTypeOf` に委譲する（これらの型では代入可能性＝部分型関係）。
 * - `equals` は同じクラスかどうか。定数型は値も比べるので個別に上書きする。
 * - `relateToSpecial` は、どの型にも共通の「上端 mixed・下端 never・union」との関係を返す。
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
     * 全型に共通する相手との関係:
     * - never はあらゆる型の部分型（= Yes）
     * - mixed はどの型でもありうる（= Maybe）
     * - union は「全メンバを部分型に持つか」の AND
     *
     * いずれでもなければ null を返し、呼び出し側の固有判定に委ねる。
     */
    protected function relateToSpecial(Type $type): ?TrinaryLogic
    {
        if ($type instanceof NeverType) {
            return TrinaryLogic::Yes;
        }

        if ($type instanceof MixedType) {
            return TrinaryLogic::Maybe;
        }

        if ($type instanceof UnionType) {
            $result = TrinaryLogic::Yes;
            foreach ($type->getTypes() as $member) {
                $result = $result->and($this->isSuperTypeOf($member));
            }

            return $result;
        }

        return null;
    }
}
