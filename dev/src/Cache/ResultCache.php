<?php

declare(strict_types=1);

namespace Ministan\Cache;

/**
 * ファイル単位の解析結果キャッシュ。
 *
 * 大規模コードベースで毎回全ファイルを解析するのは遅い。ファイルの**内容**が変わって
 * いなければ結果も変わらない——という事実を使い、内容ハッシュをキーに結果を保存する。
 * PHPStan の結果キャッシュを大きく簡略化したもの。
 *
 * キーには salt（解析器のバージョン＋レベル）を混ぜる。解析ロジックやレベルが変われば
 * 自動的にキャッシュが無効になる。ファイルパスはキーに含めない（同一内容なら結果も同一）。
 */
final class ResultCache
{
    public function __construct(
        private readonly string $directory,
        private readonly string $salt,
    ) {
    }

    /**
     * @return list<array{message: string, line: int}>|null ヒットすれば結果、無ければ null
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
