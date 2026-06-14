<?php

declare(strict_types=1);

$a = 42;
$b = $a + 1;
$c = 'hello';
$d = $c . ' world';
$e = $a < $b;
$f = -$a;

function label(int $n): string
{
    $text = 'n=' . $n;

    return $text;
}
