<?php

declare(strict_types=1);

function plus_one(?int $x): int
{
    if ($x === null) {
        return 0;
    }

    $y = $x + 1; // 早期 return により $x は int → $y : int

    return $y;
}

function from_assert(mixed $value): int
{
    assert(is_int($value));

    $r = $value + 1; // assert により $value は int → $r : int

    return $r;
}

interface Shape
{
}

class Circle implements Shape
{
    public function radius(): int
    {
        return 1;
    }
}

function area(Shape $shape): int
{
    return match (true) {
        $shape instanceof Circle => $shape->radius(), // match の腕で Circle に絞り込み
        default => 0,
    };
}
