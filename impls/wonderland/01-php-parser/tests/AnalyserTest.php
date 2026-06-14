<?php

declare(strict_types=1);

namespace Ministan\Tests;

use Ministan\Analyser\Analyser;
use Ministan\Rules\Functions\NoVarDumpRule;
use Ministan\Rules\RuleRegistry;
use PHPUnit\Framework\TestCase;

final class AnalyserTest extends TestCase
{
    private function analyser(): Analyser
    {
        return new Analyser(new RuleRegistry([new NoVarDumpRule()]));
    }

    public function testCleanFileReportsNoErrors(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/valid.php');

        self::assertSame([], $errors);
    }

    public function testSyntaxErrorIsReportedWithLine(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/syntax-error.php');

        self::assertCount(1, $errors);
        self::assertStringContainsString('Syntax error', $errors[0]->message);
        self::assertSame(6, $errors[0]->line);
    }

    public function testMissingFileIsReported(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/does-not-exist.php');

        self::assertCount(1, $errors);
        self::assertStringContainsString('was not found', $errors[0]->message);
    }

    public function testVarDumpIsDetectedAtItsLine(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/var-dump.php');

        self::assertCount(1, $errors);
        self::assertSame('Called var_dump().', $errors[0]->message);
        self::assertSame(7, $errors[0]->line);
    }
}
