<?php

declare(strict_types=1);

function needs_int(int $n): int
{
    return $n;
}

needs_int('hello'); // string into an int parameter → type mismatch

/**
 * @return string
 */
function make_string(): string
{
    return 42; // return value mismatches the declaration (detected at level 6 and above)
}
