<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;

/**
 * 1 つの docblock から取り出した型情報。`@return`・`@param`・`@var`。
 */
final readonly class ParsedPhpDoc
{
    /**
     * @param array<string, Type> $paramTypes パラメータ名（$ なし）→ 型
     * @param array<string, Type> $varTypes   変数名（$ なし、無名は ''）→ 型
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
