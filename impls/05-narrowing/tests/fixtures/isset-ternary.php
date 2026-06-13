<?php

declare(strict_types=1);

function pick(): string
{
    // $name は一度も代入されないが、isset で守られた三項なら参照は安全。
    return isset($name) ? $name : 'anon';
}
