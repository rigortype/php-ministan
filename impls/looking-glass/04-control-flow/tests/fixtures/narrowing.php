<?php

declare(strict_types=1);

function plus_one(?int $x): int
{
    if ($x === null) {
        return 0;
    }

    $y = $x + 1; // early return makes $x int → $y : int

    return $y;
}

function from_assert(mixed $value): int
{
    assert(is_int($value));

    $r = $value + 1; // assert makes $value int → $r : int

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
        $shape instanceof Circle => $shape->radius(), // match arm narrows to Circle
        default => 0,
    };
}
