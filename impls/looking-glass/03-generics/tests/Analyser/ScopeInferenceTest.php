<?php

declare(strict_types=1);

namespace Ministan\Tests\Analyser;

use Ministan\Analyser\Scope;
use Ministan\Type\Constant\ConstantIntegerType;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\TestCase;

final class ScopeInferenceTest extends TestCase
{
    public function testLiteralsBecomeConstantTypes(): void
    {
        $scope = Scope::createForFile();

        self::assertSame('42', $scope->getType(new Int_(42))->describe());
        self::assertSame("'foo'", $scope->getType(new String_('foo'))->describe());
        self::assertSame('float', $scope->getType(new Float_(1.5))->describe());
    }

    public function testConstFetch(): void
    {
        $scope = Scope::createForFile();

        self::assertSame('true', $scope->getType(new ConstFetch(new Name('true')))->describe());
        self::assertSame('false', $scope->getType(new ConstFetch(new Name('false')))->describe());
        self::assertSame('null', $scope->getType(new ConstFetch(new Name('null')))->describe());
    }

    public function testArithmeticAndConcatAndComparison(): void
    {
        $scope = Scope::createForFile();

        self::assertSame('int', $scope->getType(new Plus(new Int_(1), new Int_(2)))->describe());
        self::assertSame('string', $scope->getType(new Concat(new String_('a'), new String_('b')))->describe());
        self::assertSame('bool', $scope->getType(new Smaller(new Int_(1), new Int_(2)))->describe());
        self::assertSame('int', $scope->getType(new UnaryMinus(new Int_(5)))->describe());
    }

    public function testVariableLookupAndMixedFallback(): void
    {
        $scope = Scope::createForFile()->assignVariable('a', new ConstantIntegerType(42));

        self::assertSame('42', $scope->getType(new Variable('a'))->describe());
        self::assertSame('mixed', $scope->getType(new Variable('unknown'))->describe());
    }
}
