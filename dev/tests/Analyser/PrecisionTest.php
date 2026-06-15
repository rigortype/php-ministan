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
        // area(): int returns match(...). If the match expression's result type is inferred
        // as int, the return-type check passes even at the strictest level 9.
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

        // $current = $prev is a union containing 'item', since on the previous iteration $prev could become 'item'.
        self::assertMatchesRegularExpression("/\\\$current\s+:\s+'item'\|'start'$/m", $output);
    }
}
