<?php

declare(strict_types=1);

namespace Ministan\Tests\Reflection;

use Ministan\Reflection\PhpDocTypeResolver;
use PHPUnit\Framework\TestCase;

final class PhpDocTypeResolverTest extends TestCase
{
    private PhpDocTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PhpDocTypeResolver();
    }

    public function testReturnTag(): void
    {
        $doc = $this->resolver->parse('/** @return int */');

        self::assertNotNull($doc->returnType);
        self::assertSame('int', $doc->returnType->describe());
    }

    public function testGenericArrayAndListAndBrackets(): void
    {
        self::assertSame(
            'array<int, string>',
            $this->resolver->parse('/** @return array<int, string> */')->returnType?->describe(),
        );
        self::assertSame(
            'array<int, string>',
            $this->resolver->parse('/** @return list<string> */')->returnType?->describe(),
        );
        self::assertSame(
            'array<mixed, string>',
            $this->resolver->parse('/** @return string[] */')->returnType?->describe(),
        );
    }

    public function testParamAndNullable(): void
    {
        $doc = $this->resolver->parse('/** @param ?int $count */');

        self::assertSame('int|null', $doc->paramTypes['count']->describe());
    }

    public function testVarTag(): void
    {
        $doc = $this->resolver->parse('/** @var Foo\Bar $x */');

        self::assertSame('Foo\Bar', $doc->varTypes['x']->describe());
    }

    public function testEmptyDocBlock(): void
    {
        $doc = $this->resolver->parse(null);

        self::assertNull($doc->returnType);
        self::assertSame([], $doc->paramTypes);
    }
}
