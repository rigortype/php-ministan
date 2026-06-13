<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Type\BooleanType;
use Ministan\Type\Constant\ConstantBooleanType;
use Ministan\Type\Constant\ConstantIntegerType;
use Ministan\Type\Constant\ConstantStringType;
use Ministan\Type\FloatType;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\NullType;
use Ministan\Type\ObjectType;
use Ministan\Type\StringType;
use Ministan\Type\Type;
use Ministan\Type\TypeCombinator;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;

/**
 * ある地点で「いま何が分かっているか」を表す不変オブジェクト。
 *
 * Part 2 では「変数が定義済みか」だけを持っていた。Part 4 でそれを **変数→型** の
 * 対応へ育て、さらに {@see getType()} で任意の式の型を推論できるようにする。
 * これが PHPStan の `Scope::getType()` に対応する、型推論の本体。
 */
final readonly class Scope
{
    /**
     * @param array<string, Type> $variableTypes 定義済み変数名 → その型
     */
    private function __construct(
        private array $variableTypes,
    ) {
    }

    public static function createForFile(): self
    {
        return self::createForFunction();
    }

    public static function createForFunction(): self
    {
        $superglobals = [
            'GLOBALS', '_SERVER', '_GET', '_POST', '_FILES',
            '_COOKIE', '_SESSION', '_REQUEST', '_ENV',
        ];

        $variableTypes = [];
        foreach ($superglobals as $name) {
            $variableTypes[$name] = new MixedType();
        }

        return new self($variableTypes);
    }

    public function hasVariable(string $name): bool
    {
        return isset($this->variableTypes[$name]);
    }

    /**
     * 変数の型。未定義なら mixed に縮退する（non-rejecting）。
     */
    public function getVariableType(string $name): Type
    {
        return $this->variableTypes[$name] ?? new MixedType();
    }

    public function assignVariable(string $name, Type $type): self
    {
        return new self([...$this->variableTypes, $name => $type]);
    }

    /**
     * 2 つのスコープを合流する。両方で定義された変数は型を **union** で合併する
     * （then で int・else で string なら、合流後は int|string）。片方だけの変数は
     * 楽観的に残し、偽陽性を出さない。
     */
    public function mergeWith(self $other): self
    {
        $merged = $this->variableTypes;
        foreach ($other->variableTypes as $name => $type) {
            $merged[$name] = isset($merged[$name])
                ? TypeCombinator::union($merged[$name], $type)
                : $type;
        }

        return new self($merged);
    }

    /**
     * 式の型を推論する。型システムが初めて「動いて見える」場所。
     *
     * Part 4 ではリテラル・定数・変数参照・基本的な二項/単項演算を扱う。
     * メソッド呼び出しや配列アクセスは、リフレクション（Part 6）以降で精密化する。
     */
    public function getType(Expr $expr): Type
    {
        return match (true) {
            // --- リテラル → 定数型 ---
            $expr instanceof Scalar\Int_    => new ConstantIntegerType($expr->value),
            $expr instanceof Scalar\String_ => new ConstantStringType($expr->value),
            $expr instanceof Scalar\Float_  => new FloatType(), // 定数 float 型は後章

            // --- true / false / null ---
            $expr instanceof Expr\ConstFetch => $this->constFetchType($expr),

            // --- 変数参照 ---
            $expr instanceof Expr\Variable => is_string($expr->name)
                ? $this->getVariableType($expr->name)
                : new MixedType(),

            // --- オブジェクト生成・呼び出し（リフレクションを使う）---
            $expr instanceof Expr\New_ => $expr->class instanceof Name
                ? new ObjectType($expr->class->toString())
                : new MixedType(),
            $expr instanceof Expr\MethodCall => $this->methodCallType($expr),
            $expr instanceof Expr\FuncCall => $this->funcCallType($expr),

            // --- 文字列連結は常に string ---
            $expr instanceof Expr\BinaryOp\Concat => new StringType(),

            // --- 算術 ---
            $expr instanceof Expr\BinaryOp\Plus,
            $expr instanceof Expr\BinaryOp\Minus,
            $expr instanceof Expr\BinaryOp\Mul => $this->arithmeticType($expr),

            $expr instanceof Expr\UnaryMinus,
            $expr instanceof Expr\UnaryPlus => $this->toNumeric($this->getType($expr->expr)),

            // --- 比較・論理は常に bool ---
            $expr instanceof Expr\BinaryOp\Identical,
            $expr instanceof Expr\BinaryOp\NotIdentical,
            $expr instanceof Expr\BinaryOp\Equal,
            $expr instanceof Expr\BinaryOp\NotEqual,
            $expr instanceof Expr\BinaryOp\Smaller,
            $expr instanceof Expr\BinaryOp\SmallerOrEqual,
            $expr instanceof Expr\BinaryOp\Greater,
            $expr instanceof Expr\BinaryOp\GreaterOrEqual,
            $expr instanceof Expr\BinaryOp\BooleanAnd,
            $expr instanceof Expr\BinaryOp\BooleanOr,
            $expr instanceof Expr\BooleanNot => new BooleanType(),

            // --- 分からないものは mixed に縮退 ---
            default => new MixedType(),
        };
    }

    private function methodCallType(Expr\MethodCall $expr): Type
    {
        if (!$expr->name instanceof Identifier) {
            return new MixedType();
        }

        $objectType = $this->getType($expr->var);
        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if (!$objectType instanceof ObjectType || $provider === null || !$provider->hasClass($objectType->className)) {
            return new MixedType();
        }

        $class = $provider->getClass($objectType->className);
        if (!$class->hasMethod($expr->name->toString())) {
            return new MixedType();
        }

        return $class->getMethod($expr->name->toString())->returnType;
    }

    private function funcCallType(Expr\FuncCall $expr): Type
    {
        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if (!$expr->name instanceof Name || $provider === null || !$provider->hasFunction($expr->name->toString())) {
            return new MixedType();
        }

        return $provider->getFunction($expr->name->toString())->returnType;
    }

    private function constFetchType(Expr\ConstFetch $expr): Type
    {
        return match ($expr->name->toLowerString()) {
            'true' => new ConstantBooleanType(true),
            'false' => new ConstantBooleanType(false),
            'null' => new NullType(),
            default => new MixedType(),
        };
    }

    private function arithmeticType(Expr\BinaryOp $expr): Type
    {
        $left = $this->getType($expr->left);
        $right = $this->getType($expr->right);

        $int = new IntegerType();
        if ($int->isSuperTypeOf($left)->yes() && $int->isSuperTypeOf($right)->yes()) {
            return new IntegerType();
        }

        if ($this->isNumeric($left) && $this->isNumeric($right)) {
            return new FloatType();
        }

        return new MixedType();
    }

    private function toNumeric(Type $type): Type
    {
        if ((new IntegerType())->isSuperTypeOf($type)->yes()) {
            return new IntegerType();
        }

        return $this->isNumeric($type) ? new FloatType() : new MixedType();
    }

    private function isNumeric(Type $type): bool
    {
        return (new IntegerType())->isSuperTypeOf($type)->yes()
            || (new FloatType())->isSuperTypeOf($type)->yes();
    }
}
