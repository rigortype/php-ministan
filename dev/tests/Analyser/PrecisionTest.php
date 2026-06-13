<?php

declare(strict_types=1);

namespace Ministan\Tests\Analyser;

use Ministan\Analyser\Analyser;
use Ministan\Command\AnnotateCommand;
use Ministan\Rules\RuleRegistryFactory;
use PHPUnit\Framework\TestCase;

final class PrecisionTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function messages(string $fixture, int $level): array
    {
        $registry = (new RuleRegistryFactory())->createForLevel($level);
        $errors = (new Analyser($registry))->analyseFile(__DIR__ . '/../fixtures/' . $fixture);

        return array_map(static fn ($e): string => $e->message, $errors);
    }

    public function testMatchResultTypeSatisfiesDeclaredReturn(): void
    {
        // area(): int は match(...) を返す。match 式の結果型が int と推論できれば、
        // 最も厳しい level 9 でも戻り値検査を通る。
        self::assertSame([], $this->messages('narrowing.php', 9));
    }

    public function testNamedArgumentTypeMismatch(): void
    {
        $messages = $this->messages('precision.php', 5);

        self::assertContains("Parameter #2 of function box() expects int, 'big' given.", $messages);
    }

    public function testLoopWidensCarriedVariableType(): void
    {
        ob_start();
        (new AnnotateCommand())->run([__DIR__ . '/../fixtures/precision.php']);
        $output = ob_get_clean();

        // $current = $prev は、前周で $prev が 'item' になりうるため 'item' を含む union。
        self::assertMatchesRegularExpression("/\\\$current\s+:\s+'item'\|'start'$/m", $output);
    }
}
