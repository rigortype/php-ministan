<?php

declare(strict_types=1);

namespace Ministan\Console;

use Ministan\Command\AnalyseCommand;
use Ministan\Command\AnnotateCommand;

/**
 * CLI のエントリポイント。サブコマンドを振り分けるだけの薄い層。
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
              ministan analyse [options] <paths...>   ファイル／ディレクトリを解析する
              ministan annotate <file>                推論された型を表示する

            analyse options:
              --level=N                 ルールレベル（0..9, 既定 5）
              --error-format=json       JSON で出力する
              --baseline=FILE           既知の指摘を無視する
              --generate-baseline[=FILE]  現在の指摘を baseline に書き出す
              --cache[=DIR]             結果キャッシュを使う（既定 .ministan-cache）

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
