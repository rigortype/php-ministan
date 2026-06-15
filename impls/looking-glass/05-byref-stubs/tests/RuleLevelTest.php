<?php

declare(strict_types=1);

namespace Ministan\Tests;

use Ministan\Analyser\Analyser;
use Ministan\Rules\RuleRegistryFactory;
use PHPUnit\Framework\TestCase;

final class RuleLevelTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function messagesFor(string $fixture, int $level): array
    {
        $registry = (new RuleRegistryFactory())->createForLevel($level);
        $errors = (new Analyser($registry))->analyseFile(__DIR__ . '/fixtures/' . $fixture);

        return array_map(static fn ($error): string => $error->message, $errors);
    }

    public function testArgumentMismatchIsReportedFromLevel5(): void
    {
        self::assertSame([], $this->messagesFor('argument-mismatch.php', 0));

        $messages = $this->messagesFor('argument-mismatch.php', 5);
        self::assertCount(1, $messages);
        self::assertSame("Parameter #1 of function needs_int() expects int, 'hello' given.", $messages[0]);
    }

    public function testReturnMismatchIsReportedFromLevel6(): void
    {
        self::assertSame([], $this->messagesFor('return-mismatch.php', 5));

        $messages = $this->messagesFor('return-mismatch.php', 6);
        self::assertCount(1, $messages);
        self::assertSame('Should return string but returns 42.', $messages[0]);
    }

    public function testHigherLevelIncludesLowerLevelRules(): void
    {
        // Undefined methods are detected even at level 0.
        $messages = $this->messagesFor('undefined-method.php', 0);

        self::assertContains('Call to an undefined method Box::close().', $messages);
    }
}
