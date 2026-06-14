<?php

declare(strict_types=1);

function needs_int(int $n): int
{
    return $n;
}

needs_int('hello');
