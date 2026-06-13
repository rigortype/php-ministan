<?php

declare(strict_types=1);

namespace Ministan\Tests\Analyser;

use Ministan\Analyser\Scope;
use Ministan\Analyser\TypeSpecifier;
use Ministan\Type\MixedType;
use Ministan\Type\TypeCombinator;
use Ministan\Type\IntegerType;
use Ministan\Type\NullType;
use Ministan\Type\StringType;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class TypeSpecifierTest extends TestCase
{
    /**
     * 与えた条件式 1 つを含む小さなコードをパースし、その条件ノードを取り出す。
     */
    private function conditionOf(string $expr): \PhpParser\Node\Expr
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse("<?php if ($expr) {}");
        $if = $ast[0];
        assert($if instanceof \PhpParser\Node\Stmt\If_);

        return $if->cond;
    }

    public function testIsIntNarrowsBothBranches(): void
    {
        $scope = Scope::createForFile()->assignVariable('x', new MixedType());
        $specified = (new TypeSpecifier())->specify($this->conditionOf('is_int($x)'), $scope);

        self::assertSame('int', $specified->truthy->getVariableType('x')->describe());
        // mixed から int を引いても mixed のまま（mixed は分解できない）。
        self::assertSame('mixed', $specified->falsy->getVariableType('x')->describe());
    }

    public function testIsStringNarrowsFromUnion(): void
    {
        $intOrString = TypeCombinator::union(new IntegerType(), new StringType());
        $scope = Scope::createForFile()->assignVariable('x', $intOrString);

        $specified = (new TypeSpecifier())->specify($this->conditionOf('is_string($x)'), $scope);

        self::assertSame('string', $specified->truthy->getVariableType('x')->describe());
        self::assertSame('int', $specified->falsy->getVariableType('x')->describe());
    }

    public function testIdenticalNullNarrows(): void
    {
        $intOrNull = TypeCombinator::union(new IntegerType(), new NullType());
        $scope = Scope::createForFile()->assignVariable('x', $intOrNull);

        $specified = (new TypeSpecifier())->specify($this->conditionOf('$x === null'), $scope);

        self::assertSame('null', $specified->truthy->getVariableType('x')->describe());
        self::assertSame('int', $specified->falsy->getVariableType('x')->describe());
    }

    public function testNotNullNarrowsViaNegation(): void
    {
        $intOrNull = TypeCombinator::union(new IntegerType(), new NullType());
        $scope = Scope::createForFile()->assignVariable('x', $intOrNull);

        $specified = (new TypeSpecifier())->specify($this->conditionOf('$x !== null'), $scope);

        self::assertSame('int', $specified->truthy->getVariableType('x')->describe());
        self::assertSame('null', $specified->falsy->getVariableType('x')->describe());
    }

    public function testInstanceofNarrowsTruthy(): void
    {
        $scope = Scope::createForFile()->assignVariable('x', new MixedType());
        $specified = (new TypeSpecifier())->specify($this->conditionOf('$x instanceof Foo'), $scope);

        self::assertSame('Foo', $specified->truthy->getVariableType('x')->describe());
    }
}
