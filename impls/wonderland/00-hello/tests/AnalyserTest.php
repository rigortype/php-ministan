<?php

declare(strict_types=1);

namespace Ministan\Tests;

use Ministan\Analyser\Analyser;
use PHPUnit\Framework\TestCase;

final class AnalyserTest extends TestCase
{
    public function testValidFileReportsNoErrors(): void
    {
        $errors = (new Analyser())->analyseFile(__DIR__ . '/fixtures/valid.php');

        self::assertSame([], $errors);
    }

    public function testSyntaxErrorIsReportedWithLine(): void
    {
        $errors = (new Analyser())->analyseFile(__DIR__ . '/fixtures/syntax-error.php');

        self::assertCount(1, $errors);
        self::assertStringContainsString('Syntax error', $errors[0]->message);
        self::assertSame(6, $errors[0]->line);
    }

    public function testMissingFileIsReported(): void
    {
        $errors = (new Analyser())->analyseFile(__DIR__ . '/fixtures/does-not-exist.php');

        self::assertCount(1, $errors);
        self::assertStringContainsString('was not found', $errors[0]->message);
    }
}
