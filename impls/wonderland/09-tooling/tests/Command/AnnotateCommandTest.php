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
