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
echo $box->open();
