<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;

/**
 * Type information extracted from a single docblock: `@return`, `@param`, `@var`, `@template`.
 */
final readonly class ParsedPhpDoc
{
    /**
     * @param array<string, Type> $paramTypes    parameter name (without $) => type
     * @param array<string, Type> $varTypes      variable name (without $, '' if anonymous) => type
     * @param list<string>        $templateNames the type variable names this docblock declares
     */
    public function __construct(
        public ?Type $returnType,
        public array $paramTypes,
        public array $varTypes,
        public array $templateNames = [],
    ) {
    }

    public static function empty(): self
    {
        return new self(null, [], [], []);
    }
}
