<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Type\ArrayType;
use Ministan\Type\BooleanType;
use Ministan\Type\Constant\ConstantArrayType;
use Ministan\Type\Constant\ConstantBooleanType;
use Ministan\Type\Constant\ConstantIntegerType;
use Ministan\Type\Constant\ConstantStringType;
use Ministan\Type\FloatType;
use Ministan\Type\GenericObjectType;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\NullType;
use Ministan\Type\ObjectType;
use Ministan\Type\StringType;
use Ministan\Type\TemplateType;
use Ministan\Type\TemplateTypeMap;
use Ministan\Type\Type;
use Ministan\Type\TypeCombinator;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
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
     * @param Type|null $functionReturnType 現在いる関数／メソッドの宣言戻り値型（無ければ null）
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
     * 変数の型。未定義なら mixed に縮退する（non-rejecting）。
     */
    public function getVariableType(string $name): Type
    {
        return $this->variableTypes[$name] ?? new MixedType();
    }

    public function assignVariable(string $name, Type $type): self
    {
        return new self([...$this->variableTypes, $name => $type], $this->functionReturnType);
    }

    /** 関数本体に入るとき、宣言された戻り値型を覚えておく（戻り値検査が使う）。 */
    public function withFunctionReturnType(Type $type): self
    {
        return new self($this->variableTypes, $type);
    }

    public function getFunctionReturnType(): ?Type
    {
        return $this->functionReturnType;
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

        return new self($merged, $this->functionReturnType);
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

            // --- 配列リテラルとアクセス ---
            $expr instanceof Expr\Array_ => $this->arrayLiteralType($expr),
            $expr instanceof Expr\ArrayDimFetch => $this->arrayDimType($expr),

            // --- オブジェクト生成・呼び出し（リフレクションを使う）---
            $expr instanceof Expr\New_ => $expr->class instanceof Name
                ? new ObjectType($expr->class->toString())
                : new MixedType(),
            $expr instanceof Expr\MethodCall => $this->methodCallType($expr),
            $expr instanceof Expr\FuncCall => $this->funcCallType($expr),
            $expr instanceof Expr\Match_ => $this->matchType($expr),

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

    private function arrayLiteralType(Expr\Array_ $expr): Type
    {
        $keyTypes = [];
        $valueTypes = [];
        $nextInt = 0;
        $isConstant = true;

        foreach ($expr->items as $item) {
            if (!$item instanceof ArrayItem || $item->unpack) {
                $isConstant = false; // スプレッド ...$a は静的に分からない
                continue;
            }

            $valueType = $this->getType($item->value);

            if ($item->key === null) {
                $keyType = new ConstantIntegerType($nextInt);
                $nextInt++;
            } else {
                $keyType = $this->getType($item->key);
                if ($keyType instanceof ConstantIntegerType) {
                    $nextInt = max($nextInt, $keyType->value + 1);
                } elseif (!$keyType instanceof ConstantStringType) {
                    $isConstant = false; // キーが定数でなければ shape にできない
                }
            }

            $keyTypes[] = $keyType;
            $valueTypes[] = $valueType;
        }

        if ($isConstant) {
            return new ConstantArrayType($keyTypes, $valueTypes);
        }

        return new ArrayType(
            $keyTypes === [] ? new MixedType() : TypeCombinator::union(...$keyTypes),
            $valueTypes === [] ? new MixedType() : TypeCombinator::union(...$valueTypes),
        );
    }

    private function arrayDimType(Expr\ArrayDimFetch $expr): Type
    {
        if ($expr->dim === null) {
            return new MixedType(); // $arr[] は書き込み専用構文
        }

        $arrayType = $this->getType($expr->var);

        if ($arrayType instanceof ConstantArrayType) {
            return $arrayType->getOffsetValueType($this->getType($expr->dim));
        }
        if ($arrayType instanceof ArrayType) {
            return $arrayType->itemType;
        }

        return new MixedType();
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

        $returnType = $class->getMethod($expr->name->toString())->returnType;

        // ジェネリッククラスなら、型引数で戻り値の型変数を置換する（Collection<int>::get(): T → int）。
        if ($objectType instanceof GenericObjectType && $class->templateNames !== []) {
            $map = [];
            foreach ($class->templateNames as $i => $templateName) {
                if (isset($objectType->typeArguments[$i])) {
                    $map[$templateName] = $objectType->typeArguments[$i];
                }
            }

            return (new TemplateTypeMap($map))->resolve($returnType);
        }

        return $returnType;
    }

    private function funcCallType(Expr\FuncCall $expr): Type
    {
        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if (!$expr->name instanceof Name || $provider === null || !$provider->hasFunction($expr->name->toString())) {
            return new MixedType();
        }

        $function = $provider->getFunction($expr->name->toString());
        if ($function->templateNames === []) {
            return $function->returnType;
        }

        // 実引数から型変数を推論し（identity(42) なら T → 42）、戻り値型を置換する。
        $map = [];
        foreach ($expr->args as $position => $arg) {
            if (!$arg instanceof Arg) {
                continue;
            }
            $paramType = $function->parameterTypes[$position] ?? null;
            if ($paramType instanceof TemplateType && in_array($paramType->name, $function->templateNames, true)) {
                $map[$paramType->name] = $this->getType($arg->value);
            }
        }

        return (new TemplateTypeMap($map))->resolve($function->returnType);
    }

    /**
     * match 式の結果型 = 各腕の本体の型の union。各腕は、その条件で絞り込んだ
     * スコープで評価する（`$x instanceof Foo => $x->bar()` の bar() を解決できる）。
     */
    private function matchType(Expr\Match_ $expr): Type
    {
        $specifier = new TypeSpecifier();
        $matchesTrue = $expr->cond instanceof Expr\ConstFetch
            && $expr->cond->name->toLowerString() === 'true';

        $armTypes = [];
        $remaining = $this;
        foreach ($expr->arms as $arm) {
            if ($arm->conds === null) {
                $armTypes[] = $remaining->getType($arm->body); // default

                continue;
            }

            $armScope = $remaining;
            foreach ($arm->conds as $cond) {
                $specified = $matchesTrue
                    ? $specifier->specify($cond, $remaining)
                    : $specifier->specifyEquality($expr->cond, $cond, $remaining);
                $armScope = $specified->truthy;
                $remaining = $specified->falsy;
            }

            $armTypes[] = $armScope->getType($arm->body);
        }

        return $armTypes === [] ? new MixedType() : TypeCombinator::union(...$armTypes);
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
