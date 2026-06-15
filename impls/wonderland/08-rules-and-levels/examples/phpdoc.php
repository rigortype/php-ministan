<?php

declare(strict_types=1);

/**
 * @param list<string> $names
 * @return array<int, string>
 */
function shout(array $names): array
{
    return array_map(static fn (string $name): string => strtoupper($name), $names);
}

$result = shout(['a', 'b']); // array<int, string> (from @return)

/** @var int $count */
$count = compute(); // int (@var overrides mixed)
