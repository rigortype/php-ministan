<?php

declare(strict_types=1);

namespace Ministan\Output;

use Ministan\Analyser\Error;

/**
 * 解析結果を出力テキストに整形する。表・JSON など複数の表現を差し替えられる。
 * PHPStan の {@see \PHPStan\Command\ErrorFormatter\ErrorFormatter} に対応。
 */
interface ErrorFormatter
{
    /**
     * @param list<Error> $errors
     */
    public function format(array $errors): string;
}
