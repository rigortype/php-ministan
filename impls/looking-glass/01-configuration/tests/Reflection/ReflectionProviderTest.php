<?php

declare(strict_types=1);

namespace Ministan\Tests\Reflection;

use Ministan\Analyser\Parsing;
use Ministan\Reflection\ReflectionProvider;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\TrinaryLogic;
use Ministan\Type\ObjectType;
use PHPUnit\Framework\TestCase;

final class ReflectionProviderTest extends TestCase
{
    private function providerFor(string $code): ReflectionProvider
    {
        return ReflectionProvider::fromNodes(Parsing::parse("<?php\n" . $code));
    }

    public function testCollectsClassAndMethodReturnTypeFromSource(): void
    {
        $provider = $this->providerFor('class Foo { public function bar(): int { return 1; } }');

        self::assertTrue($provider->hasClass('Foo'));
        $class = $provider->getClass('Foo');
        self::assertTrue($class->hasMethod('bar'));
        self::assertSame('int', $class->getMethod('bar')->returnType->describe());
    }

    public function testResolvesInheritedMethodsAcrossSource(): void
    {
        $provider = $this->providerFor('class A { public function a(): string { return ""; } } class B extends A {}');

        self::assertTrue($provider->getClass('B')->hasMethod('a'));
        self::assertTrue($provider->getClass('B')->isSubclassOf('A'));
    }

    public function testNativeFunctionFallback(): void
    {
        $provider = $this->providerFor('');

        self::assertTrue($provider->hasFunction('strlen'));
        self::assertSame('int', $provider->getFunction('strlen')->returnType->describe());
    }

    public function testObjectTypeUsesHierarchyWhenProviderKnown(): void
    {
        $provider = $this->providerFor('class A {} class B extends A {}');
        ReflectionProviderStaticAccessor::set($provider);

        $a = new ObjectType('A');
        $b = new ObjectType('B');

        self::assertSame(TrinaryLogic::Yes, $a->isSuperTypeOf($b)); // A is a supertype of B
        self::assertSame(TrinaryLogic::No, $b->isSuperTypeOf($a));  // the reverse does not hold
    }
}
