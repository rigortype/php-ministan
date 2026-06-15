<?php

declare(strict_types=1);

namespace Ministan\Tests\Command;

use Ministan\Command\AnnotateCommand;
use PHPUnit\Framework\TestCase;

final class AnnotateCommandTest extends TestCase
{
    public function testAnnotatesInferredTypes(): void
    {
        ob_start();
        $exitCode = (new AnnotateCommand())->run([__DIR__ . '/../fixtures/annotate.php']);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);

        // No constant folding, so $a = 42 is 42 and $b = $a + 1 is int.
        self::assertMatchesRegularExpression('/\$a\s+:\s+42$/m', $output);
        self::assertMatchesRegularExpression('/\$b\s+:\s+int$/m', $output);
        self::assertMatchesRegularExpression("/\\\$c\s+:\s+'hello'$/m", $output);
        self::assertMatchesRegularExpression('/\$d\s+:\s+string$/m', $output);
        self::assertMatchesRegularExpression('/\$e\s+:\s+bool$/m', $output);
        // Inside the function: $text is string, and return is string too.
        self::assertMatchesRegularExpression('/\$text\s+:\s+string$/m', $output);
        self::assertMatchesRegularExpression('/return\s+:\s+string$/m', $output);
    }

    public function testArrayShapeInference(): void
    {
        ob_start();
        $exitCode = (new AnnotateCommand())->run([__DIR__ . '/../fixtures/array-shape.php']);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);
        self::assertMatchesRegularExpression("/\\\$row\s+:\s+array\{id: 42, name: 'ada'\}$/m", $output);
        self::assertMatchesRegularExpression('/\$id\s+:\s+42$/m', $output);
        self::assertMatchesRegularExpression("/\\\$name\s+:\s+'ada'$/m", $output);
    }

    public function testGenericsInference(): void
    {
        ob_start();
        $exitCode = (new AnnotateCommand())->run([__DIR__ . '/../fixtures/generics.php']);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);
        self::assertMatchesRegularExpression('/\$a\s+:\s+42$/m', $output);          // generic function substitution
        self::assertMatchesRegularExpression("/\\\$b\s+:\s+'hello'$/m", $output);
        self::assertMatchesRegularExpression('/\$box\s+:\s+Box<int>$/m', $output);   // @var generic
        self::assertMatchesRegularExpression('/\$value\s+:\s+int$/m', $output);      // method return value substitution
    }

    public function testPhpDocDrivesInference(): void
    {
        ob_start();
        $exitCode = (new AnnotateCommand())->run([__DIR__ . '/../fixtures/phpdoc.php']);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);
        // @return array<int, string> propagates to the call result.
        self::assertMatchesRegularExpression('/\$result\s+:\s+array<int, string>$/m', $output);
        // @var int overrides the mixed on the right-hand side.
        self::assertMatchesRegularExpression('/\$count\s+:\s+int$/m', $output);
    }
}
