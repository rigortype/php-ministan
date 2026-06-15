<?php

declare(strict_types=1);

namespace Ministan\Console;

use Ministan\Command\AnalyseCommand;
use Ministan\Command\AnnotateCommand;

/**
 * The CLI entry point. A thin layer that only dispatches to subcommands.
 */
final class Application
{
    private const string VERSION = '0.1.0-dev';

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[0] ?? null;
        $args = array_slice($argv, 1);

        return match ($command) {
            'analyse', 'analyze' => (new AnalyseCommand())->run($args),
            'annotate' => (new AnnotateCommand())->run($args),
            null, 'help', '--help', '-h' => $this->help(),
            default => $this->unknown($command),
        };
    }

    private function help(): int
    {
        echo <<<TXT
            ministan {$this->versionLine()}

            Usage:
              ministan analyse [--level=N] <file>   analyse a PHP file (N=0..9, default 5)
              ministan annotate <file>              show the inferred types

            TXT;

        return 0;
    }

    private function versionLine(): string
    {
        return self::VERSION;
    }

    private function unknown(string $command): int
    {
        fwrite(STDERR, sprintf("Unknown command: %s\n", $command));

        return 1;
    }
}
