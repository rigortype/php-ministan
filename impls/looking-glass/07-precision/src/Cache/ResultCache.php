<?php

declare(strict_types=1);

namespace Ministan\Cache;

/**
 * A per-file cache of analysis results.
 *
 * On a large codebase, analysing every file from scratch each time is slow. We use the
 * fact that if a file's **contents** have not changed, neither has its result, and store
 * results keyed by a hash of the contents. A heavily simplified version of PHPStan's
 * result cache.
 *
 * The key mixes in a salt (analyser version + level), so when the analysis logic or the
 * level changes the cache is invalidated automatically. The file path is not part of the
 * key (identical contents yield identical results).
 */
final class ResultCache
{
    public function __construct(
        private readonly string $directory,
        private readonly string $salt,
    ) {
    }

    /**
     * @return list<array{message: string, line: int}>|null the result on a hit, null otherwise
     */
    public function load(string $code): ?array
    {
        $path = $this->pathFor($code);
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param list<array{message: string, line: int}> $entries
     */
    public function save(string $code, array $entries): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }

        file_put_contents($this->pathFor($code), json_encode($entries));
    }

    private function pathFor(string $code): string
    {
        return $this->directory . '/' . sha1($this->salt . "\0" . $code) . '.json';
    }
}
