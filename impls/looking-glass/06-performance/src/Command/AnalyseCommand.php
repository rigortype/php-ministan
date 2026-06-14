<?php

declare(strict_types=1);

namespace Ministan\Command;

use Ministan\Analyser\Analyser;
use Ministan\Analyser\FileFinder;
use Ministan\Cache\ResultCache;
use Ministan\Configuration\Configuration;
use Ministan\Configuration\ConfigurationLoader;
use Ministan\Configuration\IgnoredErrorHelper;
use Ministan\Output\Baseline;
use Ministan\Output\ErrorFormatter;
use Ministan\Output\JsonErrorFormatter;
use Ministan\Output\TableErrorFormatter;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleRegistryFactory;

/**
 * `ministan analyse [options] [paths...]` の実装。
 *
 * 設定は NEON（既定 `ministan.neon`）と CLI から組み立てる。CLI が NEON を上書きする。
 *
 * オプション:
 *   --configuration=FILE   設定ファイル（既定 ministan.neon）
 *   --level=N              ルールレベル（NEON より優先）
 *   --error-format=json    JSON で出力する
 *   --baseline=FILE        既知の指摘を無視する
 *   --generate-baseline[=FILE]  現在の指摘を baseline に書き出す
 */
final class AnalyseCommand
{
    private const string DEFAULT_BASELINE = 'ministan-baseline.json';

    private const string DEFAULT_CONFIG = 'ministan.neon';

    private const string DEFAULT_CACHE = '.ministan-cache';

    /**
     * @param list<string> $args
     */
    public function run(array $args): int
    {
        $cliLevel = null;
        $format = 'table';
        $configFile = null;
        $baselineToApply = null;
        $baselineToGenerate = null;
        $cacheDir = null;
        $cliPaths = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--level=')) {
                $cliLevel = min((int) substr($arg, strlen('--level=')), RuleRegistryFactory::MAX_LEVEL);
            } elseif ($arg === '--cache') {
                $cacheDir = self::DEFAULT_CACHE;
            } elseif (str_starts_with($arg, '--cache=')) {
                $cacheDir = substr($arg, strlen('--cache='));
            } elseif (str_starts_with($arg, '--configuration=')) {
                $configFile = substr($arg, strlen('--configuration='));
            } elseif (str_starts_with($arg, '--error-format=')) {
                $format = substr($arg, strlen('--error-format='));
            } elseif (str_starts_with($arg, '--baseline=')) {
                $baselineToApply = substr($arg, strlen('--baseline='));
            } elseif ($arg === '--generate-baseline') {
                $baselineToGenerate = self::DEFAULT_BASELINE;
            } elseif (str_starts_with($arg, '--generate-baseline=')) {
                $baselineToGenerate = substr($arg, strlen('--generate-baseline='));
            } else {
                $cliPaths[] = $arg;
            }
        }

        $config = $this->loadConfiguration($configFile);

        $level = $cliLevel ?? $config->level;
        $paths = $cliPaths !== [] ? $cliPaths : $config->paths;

        if ($paths === []) {
            fwrite(STDERR, "Usage: ministan analyse [--level=N] [paths...]  (or set paths in ministan.neon)\n");

            return 1;
        }

        $files = (new FileFinder())->find($paths);
        $registry = (new RuleRegistryFactory())->createForLevel($level, $this->instantiateRules($config));
        $cache = $cacheDir !== null ? new ResultCache($cacheDir, sprintf('ministan-1|level=%d', $level)) : null;
        $errors = (new Analyser($registry, $cache))->analyse($files);

        $errors = (new IgnoredErrorHelper($config->ignoreErrors))->filter($errors);

        if ($baselineToGenerate !== null) {
            file_put_contents($baselineToGenerate, Baseline::generate($errors));
            fwrite(STDERR, sprintf("Baseline written to %s (%d errors).\n", $baselineToGenerate, count($errors)));

            return 0;
        }

        if ($baselineToApply !== null && is_file($baselineToApply)) {
            $errors = Baseline::filter($errors, (string) file_get_contents($baselineToApply));
        }

        echo $this->formatter($format)->format($errors);

        return $errors === [] ? 0 : 1;
    }

    private function loadConfiguration(?string $configFile): Configuration
    {
        $configFile ??= is_file(self::DEFAULT_CONFIG) ? self::DEFAULT_CONFIG : null;

        if ($configFile !== null && is_file($configFile)) {
            return (new ConfigurationLoader())->load($configFile);
        }

        return new Configuration(RuleRegistryFactory::DEFAULT_LEVEL, [], [], []);
    }

    /**
     * @return list<Rule<\PhpParser\Node>>
     */
    private function instantiateRules(Configuration $config): array
    {
        $rules = [];
        foreach ($config->ruleClasses as $class) {
            if (class_exists($class) && is_subclass_of($class, Rule::class)) {
                /** @var Rule<\PhpParser\Node> $rule */
                $rule = new $class();
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    private function formatter(string $format): ErrorFormatter
    {
        return $format === 'json' ? new JsonErrorFormatter() : new TableErrorFormatter();
    }
}
