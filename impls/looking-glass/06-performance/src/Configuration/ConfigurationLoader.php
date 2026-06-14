<?php

declare(strict_types=1);

namespace Ministan\Configuration;

use Ministan\Rules\RuleRegistryFactory;
use Nette\Neon\Neon;

/**
 * NEON 設定ファイルを {@see Configuration} に読み込む。
 *
 * PHPStan が `phpstan.neon` を読むのと同じ位置づけ。本物に倣い NEON を採用する。
 *
 * ```neon
 * parameters:
 *     level: 6
 *     paths:
 *         - src
 *     ignoreErrors:
 *         - '#Call to an undefined method#'
 * rules:
 *     - App\Rules\MyRule
 * ```
 */
final class ConfigurationLoader
{
    public function load(string $file): Configuration
    {
        $data = Neon::decode((string) file_get_contents($file));
        $data = is_array($data) ? $data : [];

        $parameters = (array) ($data['parameters'] ?? []);

        return new Configuration(
            (int) ($parameters['level'] ?? RuleRegistryFactory::DEFAULT_LEVEL),
            $this->stringList($parameters['paths'] ?? []),
            $this->stringList($parameters['ignoreErrors'] ?? []),
            $this->stringList($data['rules'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return array_values(array_map(strval(...), (array) $value));
    }
}
