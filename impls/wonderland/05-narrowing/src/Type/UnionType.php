<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * 「いずれかの型」を表す合併型。`int|string`, `Foo|null` など。
 *
 * 絞り込み（narrowing）の合流で生まれる。`if` の then で int、else で string に
 * なった変数は、合流後に `int|string` になる。PHPStan の {@see \PHPStan\Type\UnionType}。
 *
 * 正規化（フラット化・重複除去・never 除去・1 個なら単型へ）は生成側の
 * {@see TypeCombinator::union()} が担う。ここでは正規化済みの構成要素を前提とする。
 */
final class UnionType implements Type
{
    /** @var list<Type> */
    private array $types;

    /**
     * @param list<Type> $types 2 個以上の構成要素（正規化済み）
     */
    public function __construct(array $types)
    {
        $this->types = $types;
    }

    /**
     * @return list<Type>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    public function describe(): string
    {
        $parts = array_map(static fn (Type $t): string => $t->describe(), $this->types);
        sort($parts); // 安定した表示のため整列

        return implode('|', $parts);
    }

    public function isSuperTypeOf(Type $type): TrinaryLogic
    {
        // 相手が union なら、各メンバとの関係を集めて extreme-identity で畳む
        // （全 Yes→Yes・全 No→No・混在は部分一致で Maybe）。
        if ($type instanceof UnionType) {
            $results = [];
            foreach ($type->types as $member) {
                $results[] = $this->isSuperTypeOf($member);
            }

            return TrinaryLogic::extremeIdentity($results);
        }

        // 単型なら、どれか 1 つのメンバが受け入れればよい（OR）。
        $result = TrinaryLogic::No;
        foreach ($this->types as $member) {
            $result = $result->or($member->isSuperTypeOf($type));
        }

        return $result;
    }

    public function accepts(Type $type): TrinaryLogic
    {
        if ($type instanceof UnionType) {
            $results = [];
            foreach ($type->types as $member) {
                $results[] = $this->accepts($member);
            }

            return TrinaryLogic::extremeIdentity($results);
        }

        $result = TrinaryLogic::No;
        foreach ($this->types as $member) {
            $result = $result->or($member->accepts($type));
        }

        return $result;
    }

    public function equals(Type $type): bool
    {
        if (!$type instanceof self || count($this->types) !== count($type->types)) {
            return false;
        }

        // 順序非依存で照合する。
        foreach ($this->types as $a) {
            foreach ($type->types as $b) {
                if ($b->equals($a)) {
                    continue 2;
                }
            }

            return false;
        }

        return true;
    }
}
