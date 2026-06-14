<?php

declare(strict_types=1);

namespace Ministan\Tests\Output;

use Ministan\Analyser\Error;
use Ministan\Output\Baseline;
use Ministan\Output\JsonErrorFormatter;
use Ministan\Output\TableErrorFormatter;
use PHPUnit\Framework\TestCase;

final class OutputTest extends TestCase
{
    public function testTableFormatterOnNoErrors(): void
    {
        self::assertSame("[OK] No errors\n", (new TableErrorFormatter())->format([]));
    }

    public function testTableFormatterGroupsByFile(): void
    {
        $output = (new TableErrorFormatter())->format([
            new Error('first', 'a.php', 3),
            new Error('second', 'a.php', 9),
        ]);

        self::assertStringContainsString('a.php', $output);
        self::assertStringContainsString('first', $output);
        self::assertStringContainsString('[ERROR] Found 2 errors', $output);
    }

    public function testJsonFormatter(): void
    {
        $output = (new JsonErrorFormatter())->format([new Error('boom', 'a.php', 1)]);
        $decoded = json_decode($output, true);

        self::assertSame(1, $decoded['totals']['file_errors']);
        self::assertSame('boom', $decoded['files']['a.php']['messages'][0]['message']);
    }

    public function testBaselineRoundTrip(): void
    {
        $errors = [
            new Error('known', 'a.php', 1),
            new Error('new', 'b.php', 2),
        ];

        $baseline = Baseline::generate([$errors[0]]);
        $remaining = Baseline::filter($errors, $baseline);

        self::assertCount(1, $remaining);
        self::assertSame('new', $remaining[0]->message);
    }
}
