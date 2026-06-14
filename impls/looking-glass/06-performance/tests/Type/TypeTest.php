<?php

declare(strict_types=1);

namespace Ministan\Tests\Type;

use Ministan\TrinaryLogic;
use Ministan\Type\BooleanType;
use Ministan\Type\Constant\ConstantBooleanType;
use Ministan\Type\Constant\ConstantIntegerType;
use Ministan\Type\Constant\ConstantStringType;
use Ministan\Type\FloatType;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\NeverType;
use Ministan\Type\NullType;
use Ministan\Type\StringType;
use Ministan\Type\Type;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase
{
    public function testDescribe(): void
    {
        self::assertSame('int', (new IntegerType())->describe());
        self::assertSame('string', (new StringType())->describe());
        self::assertSame('mixed', (new MixedType())->describe());
        self::assertSame('never', (new NeverType())->describe());
        self::assertSame('42', (new ConstantIntegerType(42))->describe());
        self::assertSame("'foo'", (new ConstantStringType('foo'))->describe());
        self::assertSame('true', (new ConstantBooleanType(true))->describe());
        self::assertSame('false', (new ConstantBooleanType(false))->describe());
    }

    #[DataProvider('superTypeCases')]
    public function testIsSuperTypeOf(Type $a, Type $b, TrinaryLogic $expected): void
    {
        self::assertSame(
            $expected,
            $a->isSuperTypeOf($b),
            sprintf('%s isSuperTypeOf %s', $a->describe(), $b->describe()),
        );
    }

    /**
     * @return iterable<string, array{Type, Type, TrinaryLogic}>
     */
    public static function superTypeCases(): iterable
    {
        $int = new IntegerType();
        $string = new StringType();
        $mixed = new MixedType();
        $never = new NeverType();

        yield 'int ⊇ int' => [$int, $int, TrinaryLogic::Yes];
        yield 'int ⊇ 42' => [$int, new ConstantIntegerType(42), TrinaryLogic::Yes];
        yield 'int ⊉ string' => [$int, $string, TrinaryLogic::No];
        yield 'int ⊇? mixed' => [$int, $mixed, TrinaryLogic::Maybe];
        yield 'int ⊇ never' => [$int, $never, TrinaryLogic::Yes];

        yield '42 ⊇ 42' => [new ConstantIntegerType(42), new ConstantIntegerType(42), TrinaryLogic::Yes];
        yield '42 ⊉ 43' => [new ConstantIntegerType(42), new ConstantIntegerType(43), TrinaryLogic::No];
        yield '42 ⊇? int' => [new ConstantIntegerType(42), $int, TrinaryLogic::Maybe];

        yield "'foo' ⊇? string" => [new ConstantStringType('foo'), $string, TrinaryLogic::Maybe];
        yield "string ⊇ 'foo'" => [$string, new ConstantStringType('foo'), TrinaryLogic::Yes];

        yield 'mixed ⊇ int' => [$mixed, $int, TrinaryLogic::Yes];
        yield 'mixed ⊇ never' => [$mixed, $never, TrinaryLogic::Yes];
        yield 'never ⊉ int' => [$never, $int, TrinaryLogic::No];
        yield 'never ⊉ mixed' => [$never, $mixed, TrinaryLogic::No];
        yield 'never ⊇ never' => [$never, $never, TrinaryLogic::Yes];

        yield 'null ⊉ int' => [new NullType(), $int, TrinaryLogic::No];
        yield 'float ⊉ int' => [new FloatType(), $int, TrinaryLogic::No];
        yield 'bool ⊇ true' => [new BooleanType(), new ConstantBooleanType(true), TrinaryLogic::Yes];
    }

    public function testAcceptsDelegatesToSuperType(): void
    {
        self::assertSame(TrinaryLogic::Yes, (new IntegerType())->accepts(new ConstantIntegerType(7)));
        self::assertSame(TrinaryLogic::No, (new IntegerType())->accepts(new StringType()));
        self::assertSame(TrinaryLogic::Maybe, (new IntegerType())->accepts(new MixedType()));
    }

    public function testEquals(): void
    {
        self::assertTrue((new IntegerType())->equals(new IntegerType()));
        self::assertFalse((new IntegerType())->equals(new StringType()));
        self::assertTrue((new ConstantIntegerType(1))->equals(new ConstantIntegerType(1)));
        self::assertFalse((new ConstantIntegerType(1))->equals(new ConstantIntegerType(2)));
        self::assertFalse((new ConstantIntegerType(1))->equals(new IntegerType()));
    }

    public function testTrinaryLogic(): void
    {
        self::assertSame(TrinaryLogic::No, TrinaryLogic::Yes->negate());
        self::assertSame(TrinaryLogic::Maybe, TrinaryLogic::Maybe->negate());
        self::assertSame(TrinaryLogic::No, TrinaryLogic::Yes->and(TrinaryLogic::No));
        self::assertSame(TrinaryLogic::Maybe, TrinaryLogic::Yes->and(TrinaryLogic::Maybe));
        self::assertSame(TrinaryLogic::Yes, TrinaryLogic::No->or(TrinaryLogic::Yes));
    }
}
