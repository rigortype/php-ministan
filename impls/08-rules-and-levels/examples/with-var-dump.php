<?php

declare(strict_types=1);

function total(array $items): int
{
    $sum = 0;
    foreach ($items as $item) {
        $sum += $item;
    }

    var_dump($sum); // デバッグ用の消し忘れ

    return $sum;
}

echo total([1, 2, 3]);
