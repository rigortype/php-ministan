<?php

declare(strict_types=1);

/**
 * @template T
 *
 * @param T $value
 *
 * @return T
 */
function identity(mixed $value): mixed
{
    return $value;
}

$a = identity(42);      // T → 42
$b = identity('hello'); // T → 'hello'

/**
 * @template T
 */
class Box
{
    /**
     * @param T $item
     */
    public function __construct(private mixed $item)
    {
    }

    /**
     * @return T
     */
    public function get(): mixed
    {
        return $this->item;
    }
}

/** @var Box<int> $box */
$box = new Box(1);
$value = $box->get(); // T → int
