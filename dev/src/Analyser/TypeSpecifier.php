<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Type\BooleanType;
use Ministan\Type\FloatType;
use Ministan\Type\IntegerType;
use Ministan\Type\NullType;
use Ministan\Type\ObjectType;
use Ministan\Type\StringType;
use Ministan\Type\Type;
use Ministan\Type\TypeCombinator;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;

/**
 * 条件式から、真／偽それぞれの分岐で成り立つ型の絞り込みを導く。
 *
 * PHPStan の {@see \PHPStan\Analyser\TypeSpecifier} に対応する核。`instanceof`、
 * `is_int()` 等の型述語、`=== null`、`isset()`、`!`・`&&`・`||` を扱う。
 */
final class TypeSpecifier
{
    public function specify(Expr $condition, Scope $scope): SpecifiedTypes
    {
        return match (true) {
            $condition instanceof Expr\BooleanNot
                => $this->specify($condition->expr, $scope)->negate(),

            $condition instanceof Expr\BinaryOp\BooleanAnd,
            $condition instanceof Expr\BinaryOp\LogicalAnd
                => $this->specifyAnd($condition, $scope),

            $condition instanceof Expr\BinaryOp\BooleanOr,
            $condition instanceof Expr\BinaryOp\LogicalOr
                => $this->specifyOr($condition, $scope),

            $condition instanceof Expr\Instanceof_
                => $this->specifyInstanceof($condition, $scope),

            $condition instanceof Expr\FuncCall
                => $this->specifyTypePredicate($condition, $scope),

            $condition instanceof Expr\Isset_
                => $this->specifyIsset($condition, $scope),

            $condition instanceof Expr\BinaryOp\Identical,
            $condition instanceof Expr\BinaryOp\Equal
                => $this->specifyEquality($condition->left, $condition->right, $scope),

            $condition instanceof Expr\BinaryOp\NotIdentical,
            $condition instanceof Expr\BinaryOp\NotEqual
                => $this->specifyEquality($condition->left, $condition->right, $scope)->negate(),

            default => new SpecifiedTypes($scope, $scope),
        };
    }

    private function specifyAnd(Expr\BinaryOp $condition, Scope $scope): SpecifiedTypes
    {
        // 真: 両条件が真。偽: どちらかが偽（精密化は見送り、元のスコープ）。
        $left = $this->specify($condition->left, $scope);
        $right = $this->specify($condition->right, $left->truthy);

        return new SpecifiedTypes($right->truthy, $scope);
    }

    private function specifyOr(Expr\BinaryOp $condition, Scope $scope): SpecifiedTypes
    {
        // 偽: 両条件が偽。真: どちらかが真（精密化は見送り）。
        $left = $this->specify($condition->left, $scope);
        $right = $this->specify($condition->right, $left->falsy);

        return new SpecifiedTypes($scope, $right->falsy);
    }

    private function specifyInstanceof(Expr\Instanceof_ $condition, Scope $scope): SpecifiedTypes
    {
        if ($condition->expr instanceof Expr\Variable
            && is_string($condition->expr->name)
            && $condition->class instanceof Name
        ) {
            $truthy = $scope->assignVariable($condition->expr->name, new ObjectType($condition->class->toString()));

            // falsy 側の引き算は継承関係が要るので Part 6 まで見送り。
            return new SpecifiedTypes($truthy, $scope);
        }

        return new SpecifiedTypes($scope, $scope);
    }

    private function specifyTypePredicate(Expr\FuncCall $condition, Scope $scope): SpecifiedTypes
    {
        if (!$condition->name instanceof Name) {
            return new SpecifiedTypes($scope, $scope);
        }

        $narrowed = $this->typeForPredicate($condition->name->toLowerString());
        if ($narrowed === null) {
            return new SpecifiedTypes($scope, $scope);
        }

        $arg = $condition->args[0] ?? null;
        if (!$arg instanceof Node\Arg
            || !$arg->value instanceof Expr\Variable
            || !is_string($arg->value->name)
        ) {
            return new SpecifiedTypes($scope, $scope);
        }

        $name = $arg->value->name;
        $current = $scope->getVariableType($name);

        $truthy = $scope->assignVariable($name, $narrowed);
        $falsy = $scope->assignVariable($name, TypeCombinator::remove($current, $narrowed));

        return new SpecifiedTypes($truthy, $falsy);
    }

    private function typeForPredicate(string $function): ?Type
    {
        return match ($function) {
            'is_int', 'is_integer', 'is_long' => new IntegerType(),
            'is_string' => new StringType(),
            'is_bool' => new BooleanType(),
            'is_float', 'is_double' => new FloatType(),
            'is_null' => new NullType(),
            default => null,
        };
    }

    private function specifyIsset(Expr\Isset_ $condition, Scope $scope): SpecifiedTypes
    {
        $truthy = $scope;
        foreach ($condition->vars as $var) {
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                // 真の枝では「定義済みかつ非 null」。未定義なら mixed として定義される。
                $current = $scope->getVariableType($var->name);
                $truthy = $truthy->assignVariable($var->name, TypeCombinator::remove($current, new NullType()));
            }
        }

        return new SpecifiedTypes($truthy, $scope);
    }

    private function specifyEquality(Expr $left, Expr $right, Scope $scope): SpecifiedTypes
    {
        [$variable, $value] = $this->orientVariableAndValue($left, $right);
        if ($variable === null || $value === null) {
            return new SpecifiedTypes($scope, $scope);
        }

        $name = $variable->name;
        assert(is_string($name));

        $valueType = $scope->getType($value);
        $current = $scope->getVariableType($name);

        $truthy = $scope->assignVariable($name, $valueType);
        $falsy = $scope->assignVariable($name, TypeCombinator::remove($current, $valueType));

        return new SpecifiedTypes($truthy, $falsy);
    }

    /**
     * 等価比較の片側が「変数」、もう片側が「値の式」になるよう並べ替える。
     *
     * @return array{Expr\Variable, Expr}|array{null, null}
     */
    private function orientVariableAndValue(Expr $left, Expr $right): array
    {
        if ($left instanceof Expr\Variable && is_string($left->name)) {
            return [$left, $right];
        }

        if ($right instanceof Expr\Variable && is_string($right->name)) {
            return [$right, $left];
        }

        return [null, null];
    }
}
