<?php

declare(strict_types=1);

namespace Ministan\Type;

use Ministan\TrinaryLogic;

/**
 * 型を表す代数的オブジェクト。PHPStan の {@see \PHPStan\Type\Type} に対応する核。
 *
 * 3 つの基本演算で型どうしの関係を問う:
 *
 * - {@see describe()} … 人間向けの文字列（`int`, `'foo'`, `mixed` …）
 * - {@see isSuperTypeOf()} … 部分型関係。「$type の値はすべて自分の値か？」
 * - {@see accepts()} … 代入可能性。「自分の場所に $type の値を入れてよいか？」
 *
 * いずれも答えは {@see TrinaryLogic}。`int` は `mixed` を「たぶん」受け入れ、
 * `string` は決して `int` を受け入れない（いいえ）。この「たぶん」がレベル制の鍵。
 */
interface Type
{
    public function describe(): string;

    public function accepts(Type $type): TrinaryLogic;

    public function isSuperTypeOf(Type $type): TrinaryLogic;

    public function equals(Type $type): bool;
}
