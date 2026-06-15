<?php

declare(strict_types=1);

function first_number(string $input): string
{
    if (preg_match('/(\d+)/', $input, $matches)) {
        return $matches[0]; // $matches is a by-ref output parameter → not undefined
    }

    return '';
}

$parts = explode(',', 'a,b,c'); // list<string> from the stub → array<int, string>
$first = $parts[0];             // string
