<?php

declare(strict_types=1);

function total(array $items): int
{
    $sum = 0;
    foreach ($items as $item) {
        $sum += $item;
    }

    var_dump($sum); // a leftover debug call

    return $sum;
}

echo total([1, 2, 3]);
