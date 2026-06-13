<?php

declare(strict_types=1);

namespace Ministan\Tests\Analyser;

use Ministan\Analyser\Analyser;
use Ministan\Command\AnnotateCommand;
use Ministan\Rules\RuleRegistryFactory;
use PHPUnit\Framework\TestCase;

final class NarrowingTest extends TestCase
{
    public function testEarlyReturnAndAssertNarrowing(): void
    {
        ob_start();
        (new AnnotateCommand())->run([__DIR__ . '/../fixtures/narrowing.php']);
        $output = ob_get_clean();

        // 早期 return で $x: int → $y: int
        self::assertMatchesRegularExpression('/\$y\s+:\s+int$/m', $output);
        // assert(is_int($value)) で $value: int → $r: int
        self::assertMatchesRegularExpression('/\$r\s+:\s+int$/m', $output);
    }

    public function testMatchArmNarrowingAvoidsUndefinedMethodFalsePositive(): void
    {
        // level 0 でも未定義メソッド検出は有効。match の腕で Circle に絞り込めていれば
        // $shape->radius() は誤検出されない。
        $registry = (new RuleRegistryFactory())->createForLevel(0);
        $errors = (new Analyser($registry))->analyseFile(__DIR__ . '/../fixtures/narrowing.php');

        self::assertSame([], $errors);
    }
}
