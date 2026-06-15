<?php

// ministan stub. Never executed; parsed only to read the signatures.
// Supplies precise types that native reflection cannot express, like PHPStan's functionMap.

declare(strict_types=1);

/**
 * @return list<string>
 */
function explode(string $separator, string $string, int $limit = PHP_INT_MAX): array {}

/**
 * @param string[] $matches
 */
function preg_match(string $pattern, string $subject, array &$matches = []): int {}
