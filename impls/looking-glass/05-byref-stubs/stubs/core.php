<?php

// ministan のスタブ。実行されず、シグネチャを読むためだけにパースされる。
// ネイティブのリフレクションでは表現できない精密な型を、PHPStan の functionMap のように補う。

declare(strict_types=1);

/**
 * @return list<string>
 */
function explode(string $separator, string $string, int $limit = PHP_INT_MAX): array {}

/**
 * @param string[] $matches
 */
function preg_match(string $pattern, string $subject, array &$matches = []): int {}
