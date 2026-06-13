<?php

declare(strict_types=1);

namespace Ministan\Type;

/**
 * 型を合成・分解する操作の置き場。PHPStan の {@see \PHPStan\Type\TypeCombinator}。
 *
 * 型クラス自身ではなくここに集約するのは、`union`/`remove` が「複数の型をまたいで
 * 正規化する」横断的な操作だから。各型は自分の関係だけ知っていればよい。
 */
final class TypeCombinator
{
    /**
     * 複数の型を合併し、正規化する。
     * フラット化 → never 除去 → mixed 吸収 → 重複除去 → 0個は never・1個は単型。
     */
    public static function union(Type ...$types): Type
    {
        $flattened = [];
        foreach ($types as $type) {
            if ($type instanceof UnionType) {
                foreach ($type->getTypes() as $member) {
                    $flattened[] = $member;
                }
            } else {
                $flattened[] = $type;
            }
        }

        $result = [];
        foreach ($flattened as $type) {
            if ($type instanceof NeverType) {
                continue; // never は合併に寄与しない
            }
            if ($type instanceof MixedType) {
                return new MixedType(); // mixed は全てを吸収する
            }

            // 既存メンバが $type の上位型なら、$type は不要（int があれば 0 は要らない）。
            foreach ($result as $existing) {
                if ($existing->isSuperTypeOf($type)->yes()) {
                    continue 2;
                }
            }

            // 逆に $type が上位型なら、その部分型の既存メンバを取り除く（0 を捨てて int を残す）。
            $result = array_values(array_filter(
                $result,
                static fn (Type $existing): bool => !$type->isSuperTypeOf($existing)->yes(),
            ));
            $result[] = $type;
        }

        return match (count($result)) {
            0 => new NeverType(),
            1 => $result[0],
            default => new UnionType(array_values($result)),
        };
    }

    /**
     * $from から $typeToRemove に含まれる部分を取り除く。
     * 例: remove(int|null, null) = int。else 分岐の絞り込みで使う。
     */
    public static function remove(Type $from, Type $typeToRemove): Type
    {
        if ($from instanceof UnionType) {
            $kept = [];
            foreach ($from->getTypes() as $member) {
                if (!$typeToRemove->isSuperTypeOf($member)->yes()) {
                    $kept[] = $member;
                }
            }

            return self::union(...$kept);
        }

        if ($typeToRemove->isSuperTypeOf($from)->yes()) {
            return new NeverType();
        }

        return $from;
    }
}
