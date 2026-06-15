<?php

declare(strict_types=1);

function pick(): string
{
    // $name is never assigned, but an isset-guarded ternary makes the reference safe.
    return isset($name) ? $name : 'anon';
}
