<?php

declare(strict_types=1);

namespace Ministan\Tests;

use Ministan\Analyser\Analyser;
use Ministan\Rules\Functions\NoVarDumpRule;
use Ministan\Rules\Methods\CallToUndefinedMethodRule;
use Ministan\Rules\RuleRegistry;
use Ministan\Rules\Variables\UndefinedVariableRule;
use PHPUnit\Framework\TestCase;

final class AnalyserTest extends TestCase
{
    private function analyser(): Analyser
    {
        return new Analyser(new RuleRegistry([
            new NoVarDumpRule(),
            new UndefinedVariableRule(),
            new CallToUndefinedMethodRule(),
        ]));
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

    public function testUndefinedVariableIsReported(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/undefined-variable.php');

        self::assertCount(1, $errors);
        self::assertSame('Undefined variable: $greetnig', $errors[0]->message);
        self::assertSame(9, $errors[0]->line);
    }

    /**
     * パラメータ・代入・foreach・アロー関数・条件分岐をまたいで定義された変数は、
     * 偽陽性を出してはならない（non-rejecting の回帰テスト）。
     */
    public function testScopedVariablesProduceNoFalsePositives(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/scoped-variables.php');

        self::assertSame([], $errors);
    }

    public function testSuperglobalsAndCoalesceAreSafe(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/superglobals.php');

        self::assertSame([], $errors);
    }

    /**
     * Part 2 で積み残した「isset で守られた三項」の取りこぼしが、
     * Part 5 の絞り込みで解消されたことの回帰テスト。
     */
    public function testIssetGuardedTernaryProducesNoFalsePositive(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/isset-ternary.php');

        self::assertSame([], $errors);
    }

    public function testDefinedMethodCallIsAccepted(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/method-call.php');

        self::assertSame([], $errors);
    }

    public function testUndefinedMethodIsReported(): void
    {
        $errors = $this->analyser()->analyseFile(__DIR__ . '/fixtures/undefined-method.php');

        self::assertCount(1, $errors);
        self::assertSame('Call to an undefined method Box::close().', $errors[0]->message);
    }
}
