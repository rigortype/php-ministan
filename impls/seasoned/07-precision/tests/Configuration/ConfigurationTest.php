<?php

declare(strict_types=1);

namespace Ministan\Tests\Configuration;

use Ministan\Analyser\Analyser;
use Ministan\Analyser\Scope;
use Ministan\Configuration\ConfigurationLoader;
use Ministan\Configuration\IgnoredErrorHelper;
use Ministan\Analyser\Error;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use Ministan\Rules\RuleRegistryFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Echo_;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testLoadsNeon(): void
    {
        $config = (new ConfigurationLoader())->load(__DIR__ . '/../fixtures/config.neon');

        self::assertSame(7, $config->level);
        self::assertSame(['src', 'tests'], $config->paths);
        self::assertSame(['#boom#'], $config->ignoreErrors);
        self::assertSame(['Ministan\Rules\Functions\NoVarDumpRule'], $config->ruleClasses);
    }

    public function testIgnoredErrorHelperFiltersByPattern(): void
    {
        $helper = new IgnoredErrorHelper(['#undefined#i']);
        $remaining = $helper->filter([
            new Error('Undefined variable: $x', 'a.php', 1),
            new Error('Called var_dump().', 'a.php', 2),
        ]);

        self::assertCount(1, $remaining);
        self::assertSame('Called var_dump().', $remaining[0]->message);
    }

    public function testCustomRuleCanBeRegistered(): void
    {
        $customRule = new class implements Rule {
            public function getNodeType(): string
            {
                return Echo_::class;
            }

            public function processNode(Node $node, Scope $scope): array
            {
                return [new RuleError('echo is discouraged.', $node->getStartLine())];
            }
        };

        $registry = (new RuleRegistryFactory())->createForLevel(0, [$customRule]);
        $errors = (new Analyser($registry))->analyseFile(__DIR__ . '/../fixtures/valid.php');

        self::assertCount(1, $errors);
        self::assertSame('echo is discouraged.', $errors[0]->message);
    }
}
