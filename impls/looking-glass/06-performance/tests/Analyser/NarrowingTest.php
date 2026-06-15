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

        // Early return makes $x: int → $y: int
        self::assertMatchesRegularExpression('/\$y\s+:\s+int$/m', $output);
        // assert(is_int($value)) makes $value: int → $r: int
        self::assertMatchesRegularExpression('/\$r\s+:\s+int$/m', $output);
    }

    public function testMatchArmNarrowingAvoidsUndefinedMethodFalsePositive(): void
    {
        // Undefined method detection is active even at level 0. If the match arm narrows
        // to Circle, $shape->radius() is not flagged as a false positive.
        $registry = (new RuleRegistryFactory())->createForLevel(0);
        $errors = (new Analyser($registry))->analyseFile(__DIR__ . '/../fixtures/narrowing.php');

        self::assertSame([], $errors);
    }
}
