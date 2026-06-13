<?php

declare(strict_types=1);

function needs_int(int $n): int
{
    return $n;
}

needs_int('hello'); // string を int パラメータに → 型不一致

/**
 * @return string
 */
function make_string(): string
{
    return 42; // 戻り値が宣言と不一致（level 6 以上で検出）
}
