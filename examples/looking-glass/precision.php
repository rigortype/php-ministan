<?php

declare(strict_types=1);

// ループの型ワイドニング: 2 周目以降は前周の代入を踏まえる。
function last_label(array $items): string
{
    $prev = 'start';
    foreach ($items as $item) {
        $current = $prev; // 前周で $prev は 'item' になりうる → string
        $prev = 'item';
    }

    return $prev;
}

// 名前付き引数の型照合。
function box(string $name, int $size): string
{
    return $name;
}

box(name: 'x', size: 'big'); // size は int を期待 → 'big' は不一致
