<?php

declare(strict_types=1);

/**
 * @return array<int, string>
 */
function names(): array
{
    return ['a', 'b'];
}

$result = names();

/** @var int $count */
$count = compute();
