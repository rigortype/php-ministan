<?php

declare(strict_types=1);

namespace Ministan\Output;

use Ministan\Analyser\Error;

/**
 * Formats analysis results into output text. Several representations, such as table or JSON,
 * can be swapped in. Corresponds to PHPStan's {@see \PHPStan\Command\ErrorFormatter\ErrorFormatter}.
 */
interface ErrorFormatter
{
    /**
     * @param list<Error> $errors
     */
    public function format(array $errors): string;
}
