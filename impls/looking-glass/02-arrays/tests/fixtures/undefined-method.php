<?php

declare(strict_types=1);

class Box
{
    public function open(): string
    {
        return 'open';
    }
}

$box = new Box();
$box->close(); // 未定義メソッド
