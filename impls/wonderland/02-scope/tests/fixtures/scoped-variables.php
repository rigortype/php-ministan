<?php

declare(strict_types=1);

function sum(array $numbers): int
{
    $total = 0;
    foreach ($numbers as $key => $number) {
        $total += $number;
    }

    return $total;
}

$double = static fn (int $x): int => $x * 2;

$message = 'result';
if ($message !== '') {
    $label = strtoupper($message);
}

echo sum([1, 2, 3]) + $double(4);
