<?php

declare(strict_types=1);

namespace Ministan\Tests\Type;

use Ministan\TrinaryLogic;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\NeverType;
use Ministan\Type\NullType;
use Ministan\Type\StringType;
use Ministan\Type\TypeCombinator;
use Ministan\Type\UnionType;
use PHPUnit\Framework\TestCase;

final class TypeCombinatorTest extends TestCase
{
    public function testUnionOfTwoAtomsDescribesSorted(): void
    {
        $union = TypeCombinator::union(new StringType(), new IntegerType());

        self::assertInstanceOf(UnionType::class, $union);
        self::assertSame('int|string', $union->describe());
    }

    public function testUnionDeduplicatesAndCollapses(): void
    {
        self::assertSame('int', TypeCombinator::union(new IntegerType(), new IntegerType())->describe());
    }

    public function testUnionFlattensNestedAndDropsNever(): void
    {
        $inner = TypeCombinator::union(new IntegerType(), new StringType());
        $union = TypeCombinator::union($inner, new NullType(), new NeverType());

        self::assertSame('int|null|string', $union->describe());
    }

    public function testMixedAbsorbs(): void
    {
        self::assertInstanceOf(
            MixedType::class,
            TypeCombinator::union(new IntegerType(), new MixedType()),
        );
    }

    public function testRemoveFromUnion(): void
    {
        $intOrNull = TypeCombinator::union(new IntegerType(), new NullType());

        self::assertSame('int', TypeCombinator::remove($intOrNull, new NullType())->describe());
    }

    public function testRemoveEntireAtomYieldsNever(): void
    {
        self::assertInstanceOf(
            NeverType::class,
            TypeCombinator::remove(new NullType(), new NullType()),
        );
    }

    public function testUnionIsSuperTypeOfMembers(): void
    {
        $intOrString = TypeCombinator::union(new IntegerType(), new StringType());

        self::assertSame(TrinaryLogic::Yes, $intOrString->isSuperTypeOf(new IntegerType()));
        self::assertSame(TrinaryLogic::No, $intOrString->isSuperTypeOf(new NullType()));
    }
}
