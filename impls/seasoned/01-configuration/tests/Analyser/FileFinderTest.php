<?php

declare(strict_types=1);

namespace Ministan\Tests\Analyser;

use Ministan\Analyser\FileFinder;
use PHPUnit\Framework\TestCase;

final class FileFinderTest extends TestCase
{
    public function testReturnsSingleFileAsIs(): void
    {
        $file = __DIR__ . '/../fixtures/valid.php';

        self::assertSame([$file], (new FileFinder())->find([$file]));
    }

    public function testRecursesDirectoriesForPhpFilesOnly(): void
    {
        $found = (new FileFinder())->find([__DIR__ . '/../fixtures']);

        self::assertNotEmpty($found);
        foreach ($found as $file) {
            self::assertStringEndsWith('.php', $file);
        }
        self::assertContains(realpath(__DIR__ . '/../fixtures/valid.php'), array_map('realpath', $found));
    }
}
