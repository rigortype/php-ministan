<?php

declare(strict_types=1);

namespace Ministan\Tests\Type;

use Ministan\Type\Constant\ConstantArrayType;
use Ministan\Type\Constant\ConstantIntegerType;
use Ministan\Type\Constant\ConstantStringType;
use PHPUnit\Framework\TestCase;

final class ConstantArrayTypeTest extends TestCase
{
    public function testDescribe(): void
    {
        $array = new ConstantArrayType(
            [new ConstantStringType('id'), new ConstantStringType('name')],
            [new ConstantIntegerType(42), new ConstantStringType('ada')],
        );

        self::assertSame("array{id: 42, name: 'ada'}", $array->describe());
    }

    public function testEmptyArray(): void
    {
        self::assertSame('array{}', (new ConstantArrayType([], []))->describe());
    }

    public function testOffsetValueByConstantKey(): void
    {
        $array = new ConstantArrayType(
            [new ConstantStringType('id')],
            [new ConstantIntegerType(42)],
        );

        self::assertSame('42', $array->getOffsetValueType(new ConstantStringType('id'))->describe());
    }

    public function testIterableValueIsUnionOfValues(): void
    {
        $array = new ConstantArrayType(
            [new ConstantIntegerType(0), new ConstantIntegerType(1)],
            [new ConstantIntegerType(1), new ConstantStringType('x')],
        );

        self::assertSame("'x'|1", $array->getIterableValueType()->describe());
    }
}
