<?php

declare(strict_types=1);

// Loop type widening: from the 2nd iteration on, the previous iteration's assignment is taken into account.
function last_label(array $items): string
{
    $prev = 'start';
    foreach ($items as $item) {
        $current = $prev; // on the previous iteration $prev could become 'item' → string
        $prev = 'item';
    }

    return $prev;
}

// Type matching of named arguments.
function box(string $name, int $size): string
{
    return $name;
}

box(name: 'x', size: 'big'); // size expects int → 'big' is a mismatch
