<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;

/**
 * Type information extracted from a single docblock: `@return`, `@param`, `@var`.
 */
final readonly class ParsedPhpDoc
{
    /**
     * @param array<string, Type> $paramTypes parameter name (without $) => type
     * @param array<string, Type> $varTypes   variable name (without $, '' if anonymous) => type
     */
    public function __construct(
        public ?Type $returnType,
        public array $paramTypes,
        public array $varTypes,
    ) {
    }

    public static function empty(): self
    {
        return new self(null, [], []);
    }
}
