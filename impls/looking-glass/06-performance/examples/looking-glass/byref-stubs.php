<?php

declare(strict_types=1);

function first_number(string $input): string
{
    if (preg_match('/(\d+)/', $input, $matches)) {
        return $matches[0]; // $matches は参照渡しの出力引数 → 未定義にならない
    }

    return '';
}

$parts = explode(',', 'a,b,c'); // スタブにより list<string> → array<int, string>
$first = $parts[0];             // string
