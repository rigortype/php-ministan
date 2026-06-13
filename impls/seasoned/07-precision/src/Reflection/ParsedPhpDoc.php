<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\Type;

/**
 * 1 つの docblock から取り出した型情報。`@return`・`@param`・`@var`・`@template`。
 */
final readonly class ParsedPhpDoc
{
    /**
     * @param array<string, Type> $paramTypes    パラメータ名（$ なし）→ 型
     * @param array<string, Type> $varTypes      変数名（$ なし、無名は ''）→ 型
     * @param list<string>        $templateNames この docblock が宣言する型変数名
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
