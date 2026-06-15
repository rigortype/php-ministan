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
 * An immutable object representing "what is known at a given point".
 *
 * In Part 2 it held only "whether a variable is defined". Part 4 grows that into a
 * **variable -> type** mapping and, via {@see getType()}, lets it infer the type of
 * any expression. This is the heart of type inference, corresponding to PHPStan's
 * `Scope::getType()`.
 */
final readonly class Scope
{
    /**
     * @param array<string, Type> $variableTypes defined variable name -> its type
     * @param Type|null $functionReturnType the declared return type of the current function/method (null if none)
     */
    private function __construct(
        private array $variableTypes,
        private ?Type $functionReturnType = null,
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
     * The type of a variable. If undefined, it collapses to mixed (non-rejecting).
     */
    public function getVariableType(string $name): Type
    {
        return $this->variableTypes[$name] ?? new MixedType();
    }

    public function assignVariable(string $name, Type $type): self
    {
        return new self([...$this->variableTypes, $name => $type], $this->functionReturnType);
    }

    /** When entering a function body, remember the declared return type (used by return-type checking). */
    public function withFunctionReturnType(Type $type): self
    {
        return new self($this->variableTypes, $type);
    }

    public function getFunctionReturnType(): ?Type
    {
        return $this->functionReturnType;
    }

    /**
     * Merges two scopes. A variable defined in both has its types combined with a
     * **union** (int in the then-branch and string in the else-branch becomes
     * int|string after the merge). A variable present in only one branch is kept
     * optimistically, so no false positives are produced.
     */
    public function mergeWith(self $other): self
    {
        $merged = $this->variableTypes;
        foreach ($other->variableTypes as $name => $type) {
            $merged[$name] = isset($merged[$name])
                ? TypeCombinator::union($merged[$name], $type)
                : $type;
        }

        return new self($merged, $this->functionReturnType);
    }

    /**
     * Infers the type of an expression. The first place where the type system
     * "visibly comes to life".
     *
     * Part 4 handles literals, constants, variable references, and basic
     * binary/unary operations. Method calls and array access are refined from
     * reflection (Part 6) onward.
     */
    public function getType(Expr $expr): Type
    {
        return match (true) {
            // --- literal -> constant type ---
            $expr instanceof Scalar\Int_    => new ConstantIntegerType($expr->value),
            $expr instanceof Scalar\String_ => new ConstantStringType($expr->value),
            $expr instanceof Scalar\Float_  => new FloatType(), // constant float type comes in a later chapter

            // --- true / false / null ---
            $expr instanceof Expr\ConstFetch => $this->constFetchType($expr),

            // --- variable reference ---
            $expr instanceof Expr\Variable => is_string($expr->name)
                ? $this->getVariableType($expr->name)
                : new MixedType(),

            // --- object construction and calls (these use reflection) ---
            $expr instanceof Expr\New_ => $expr->class instanceof Name
                ? new ObjectType($expr->class->toString())
                : new MixedType(),
            $expr instanceof Expr\MethodCall => $this->methodCallType($expr),
            $expr instanceof Expr\FuncCall => $this->funcCallType($expr),

            // --- string concatenation is always string ---
            $expr instanceof Expr\BinaryOp\Concat => new StringType(),

            // --- arithmetic ---
            $expr instanceof Expr\BinaryOp\Plus,
            $expr instanceof Expr\BinaryOp\Minus,
            $expr instanceof Expr\BinaryOp\Mul => $this->arithmeticType($expr),

            $expr instanceof Expr\UnaryMinus,
            $expr instanceof Expr\UnaryPlus => $this->toNumeric($this->getType($expr->expr)),

            // --- comparison and logical operators are always bool ---
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

            // --- anything unknown collapses to mixed ---
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
