<?php

declare(strict_types=1);

// Loop type widening: from the second iteration on, the previous iteration's assignment is taken into account.
function last_label(array $items): string
{
    $prev = 'start';
    foreach ($items as $item) {
        $current = $prev; // $prev may have become 'item' on the previous iteration → string
        $prev = 'item';
    }

    return $prev;
}

// Type matching for named arguments.
function box(string $name, int $size): string
{
    return $name;
}

box(name: 'x', size: 'big'); // size expects int → 'big' is a mismatch
