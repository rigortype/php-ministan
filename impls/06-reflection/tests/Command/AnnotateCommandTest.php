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

        // 定数畳み込みはしないので $a = 42 は 42、$b = $a + 1 は int。
        self::assertMatchesRegularExpression('/\$a\s+:\s+42$/m', $output);
        self::assertMatchesRegularExpression('/\$b\s+:\s+int$/m', $output);
        self::assertMatchesRegularExpression("/\\\$c\s+:\s+'hello'$/m", $output);
        self::assertMatchesRegularExpression('/\$d\s+:\s+string$/m', $output);
        self::assertMatchesRegularExpression('/\$e\s+:\s+bool$/m', $output);
        // 関数内: $text は string、return も string。
        self::assertMatchesRegularExpression('/\$text\s+:\s+string$/m', $output);
        self::assertMatchesRegularExpression('/return\s+:\s+string$/m', $output);
    }
}
