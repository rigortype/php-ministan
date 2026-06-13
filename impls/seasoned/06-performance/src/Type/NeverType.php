<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * 最下位の型。値を一つも含まない（決して起こらない）。
 *
 * `throw` するだけの関数の戻り値や、到達しえない分岐の型。あらゆる型の部分型であり、
 * 上位型となるのは never だけ。mixed とは対極の存在。
 */
final class NeverType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'never';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        // never の上位型は never のみ。mixed に対しても「いいえ」。
        return $type instanceof self ? TrinaryLogic::Yes : TrinaryLogic::No;
    }
}
