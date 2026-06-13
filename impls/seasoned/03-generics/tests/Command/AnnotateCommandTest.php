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
        self::assertMatchesRegularExpression('/\$a\s+:\s+42$/m', $output);          // 汎関数の置換
        self::assertMatchesRegularExpression("/\\\$b\s+:\s+'hello'$/m", $output);
        self::assertMatchesRegularExpression('/\$box\s+:\s+Box<int>$/m', $output);   // @var ジェネリック
        self::assertMatchesRegularExpression('/\$value\s+:\s+int$/m', $output);      // メソッド戻り値の置換
    }

    public function testPhpDocDrivesInference(): void
    {
        ob_start();
        $exitCode = (new AnnotateCommand())->run([__DIR__ . '/../fixtures/phpdoc.php']);
        $output = ob_get_clean();

        self::assertSame(0, $exitCode);
        // @return array<int, string> が呼び出し結果に伝わる。
        self::assertMatchesRegularExpression('/\$result\s+:\s+array<int, string>$/m', $output);
        // @var int が右辺の mixed を上書きする。
        self::assertMatchesRegularExpression('/\$count\s+:\s+int$/m', $output);
    }
}
