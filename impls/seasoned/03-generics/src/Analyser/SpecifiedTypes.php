<?php

declare(strict_types=1);

namespace Ministan\Analyser;

/**
 * ある条件式が真／偽だったとき、それぞれで成り立つスコープの組。
 *
 * `if ($x instanceof Foo)` なら truthy では $x: Foo、falsy では元のまま、というふうに、
 * 条件が分岐の両側で型をどう狭めるかを表す。PHPStan の `SpecifiedTypes` に相当。
 */
final readonly class SpecifiedTypes
{
    public function __construct(
        public Scope $truthy,
        public Scope $falsy,
    ) {
    }

    /** 否定（`!`）は真偽を入れ替えるだけ。 */
    public function negate(): self
    {
        return new self($this->falsy, $this->truthy);
    }
}
