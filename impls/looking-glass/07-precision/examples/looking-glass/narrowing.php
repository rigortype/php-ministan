<?php

declare(strict_types=1);

function plus_one(?int $x): int
{
    if ($x === null) {
        return 0;
    }

    $y = $x + 1; // early return makes $x an int → $y : int

    return $y;
}

function from_assert(mixed $value): int
{
    assert(is_int($value));

    $r = $value + 1; // assert makes $value an int → $r : int

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
        $shape instanceof Circle => $shape->radius(), // narrowed to Circle in this match arm
        default => 0,
    };
}
