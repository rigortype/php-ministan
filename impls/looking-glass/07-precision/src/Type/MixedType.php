<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * 最上位の型。あらゆる値を含む。「分からない」を表す既定値でもある。
 *
 * non-rejecting の哲学では、推論に失敗したら never でも例外でもなく mixed に
 * 縮退させる。mixed はすべてを受け入れ、すべての上位型である。
 */
final class MixedType implements Type
{
    use SimpleTypeTrait;

    public function describe(): string
    {
        return 'mixed';
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        return TrinaryLogic::Yes;
    }
}
