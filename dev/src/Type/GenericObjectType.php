<?php

declare(strict_types=1);

namespace Ministan\Type;

/**
 * 型引数を伴うオブジェクト型。`Collection<int>`、`Result<string, Error>`。
 *
 * {@see ObjectType} を継承するので、未定義メソッド検出や引数検査などの「クラスを見る」
 * 処理は型引数を無視してそのまま働く。型引数は、メソッド戻り値の型パラメータ置換に使う。
 */
final class GenericObjectType extends ObjectType
{
    /**
     * @param list<Type> $typeArguments
     */
    public function __construct(
        string $className,
        public readonly array $typeArguments,
    ) {
        parent::__construct($className);
    }

    public function describe(): string
    {
        $args = implode(', ', array_map(static fn (Type $t): string => $t->describe(), $this->typeArguments));

        return $this->className . '<' . $args . '>';
    }

    public function equals(Type $type): bool
    {
        if (!$type instanceof self
            || $type->className !== $this->className
            || count($type->typeArguments) !== count($this->typeArguments)
        ) {
            return false;
        }

        foreach ($this->typeArguments as $i => $argument) {
            if (!$argument->equals($type->typeArguments[$i])) {
                return false;
            }
        }

        return true;
    }
}
