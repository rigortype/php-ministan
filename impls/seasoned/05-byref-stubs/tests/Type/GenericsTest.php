<?php

declare(strict_types=1);

namespace Ministan\Tests\Type;

use Ministan\Type\ArrayType;
use Ministan\Type\GenericObjectType;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\StringType;
use Ministan\Type\TemplateType;
use Ministan\Type\TemplateTypeMap;
use PHPUnit\Framework\TestCase;

final class GenericsTest extends TestCase
{
    public function testGenericObjectDescribe(): void
    {
        $type = new GenericObjectType('Collection', [new IntegerType(), new StringType()]);

        self::assertSame('Collection<int, string>', $type->describe());
        self::assertSame('Collection', $type->className); // ObjectType を継承
    }

    public function testSubstitutesTemplate(): void
    {
        $map = new TemplateTypeMap(['T' => new IntegerType()]);

        self::assertSame('int', $map->resolve(new TemplateType('T', new MixedType()))->describe());
    }

    public function testSubstitutesInsideComposites(): void
    {
        $map = new TemplateTypeMap(['T' => new IntegerType()]);
        $t = new TemplateType('T', new MixedType());

        self::assertSame(
            'array<int, int>',
            $map->resolve(new ArrayType(new IntegerType(), $t))->describe(),
        );
        self::assertSame(
            'Box<int>',
            $map->resolve(new GenericObjectType('Box', [$t]))->describe(),
        );
    }

    public function testUnmappedTemplateIsLeftAsIs(): void
    {
        $map = new TemplateTypeMap(['T' => new IntegerType()]);

        self::assertSame('U', $map->resolve(new TemplateType('U', new MixedType()))->describe());
    }
}
