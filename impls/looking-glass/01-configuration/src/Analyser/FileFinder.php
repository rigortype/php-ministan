<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * 解析対象のパス（ファイルまたはディレクトリ）から .php ファイルを集める。
 */
final class FileFinder
{
    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    public function find(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
            } elseif (is_dir($path)) {
                foreach ($this->phpFilesIn($path) as $file) {
                    $files[] = $file;
                }
            }
        }

        sort($files); // 出力を安定させる

        return $files;
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        /** @var SplFileInfo $info */
        foreach ($iterator as $info) {
            if ($info->isFile() && $info->getExtension() === 'php') {
                $files[] = $info->getPathname();
            }
        }

        return $files;
    }
}
