<?php

declare(strict_types=1);

namespace Ministan\Type;

/**
 * 型変数名 → 具体型 の対応。型の中の {@see TemplateType} を一括置換する。
 *
 * `identity(42)` で `T → 42` を作り、戻り値型 `T` を `42` に解決する、といった
 * substitution の本体。複合型（union・配列・ジェネリック）の中まで再帰する。
 */
final class TemplateTypeMap
{
    /**
     * @param array<string, Type> $map
     */
    public function __construct(
        private readonly array $map,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->map === [];
    }

    public function resolve(Type $type): Type
    {
        if ($type instanceof TemplateType) {
            return $this->map[$type->name] ?? $type;
        }

        if ($type instanceof UnionType) {
            return TypeCombinator::union(...array_map($this->resolve(...), $type->getTypes()));
        }

        if ($type instanceof ArrayType) {
            return new ArrayType($this->resolve($type->keyType), $this->resolve($type->itemType));
        }

        if ($type instanceof GenericObjectType) {
            return new GenericObjectType($type->className, array_map($this->resolve(...), $type->typeArguments));
        }

        return $type;
    }
}
