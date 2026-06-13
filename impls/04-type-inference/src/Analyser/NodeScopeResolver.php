<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Closure;
use Ministan\Type\BooleanType;
use Ministan\Type\FloatType;
use Ministan\Type\IntegerType;
use Ministan\Type\MixedType;
use Ministan\Type\NullType;
use Ministan\Type\StringType;
use Ministan\Type\Type;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * AST を下りながら {@see Scope} を運び、各ノードで**コールバック**を呼ぶ。
 *
 * PHPStan の {@see \PHPStan\Analyser\NodeScopeResolver} に対応する核。Part 2 では
 * ルール適用を直接抱えていたが、Part 4 で「各ノードで (node, scope) を渡して呼ぶ」
 * 汎用コールバックに一般化した。これにより、ルールを走らせる解析（{@see Analyser}）も、
 * 推論型を覗く `annotate` も、同じ走査を再利用できる。
 *
 * 走査の要は Part 2 と同じ「読み取り文脈と書き込み文脈の区別」。加えて Part 4 では、
 * 代入のたびに右辺の型を {@see Scope::getType()} で推論し、変数に結びつける。
 */
final class NodeScopeResolver
{
    /** @var Closure(Node, Scope): void */
    private Closure $nodeCallback;

    /**
     * @param callable(Node, Scope): void $nodeCallback
     */
    public function __construct(callable $nodeCallback)
    {
        $this->nodeCallback = Closure::fromCallable($nodeCallback);
    }

    /**
     * @param Node[] $stmts
     */
    public function processNodes(array $stmts, Scope $scope): void
    {
        $this->processStmts($stmts, $scope);
    }

    /**
     * @param Node[] $nodes
     */
    private function processStmts(array $nodes, Scope $scope): Scope
    {
        foreach ($nodes as $node) {
            $scope = $this->processNode($node, $scope);
        }

        return $scope;
    }

    private function processNode(Node $node, Scope $scope): Scope
    {
        ($this->nodeCallback)($node, $scope);

        return match (true) {
            $node instanceof Stmt\Function_,
            $node instanceof Stmt\ClassMethod    => $this->processFunctionLike($node, $scope),
            $node instanceof Expr\Closure        => $this->processClosure($node, $scope),
            $node instanceof Expr\ArrowFunction  => $this->processArrowFunction($node, $scope),

            $node instanceof Expr\Assign,
            $node instanceof Expr\AssignRef      => $this->processAssign($node, $scope),
            $node instanceof Expr\AssignOp       => $this->processAssignOp($node, $scope),
            $node instanceof Stmt\Foreach_       => $this->processForeach($node, $scope),
            $node instanceof Stmt\Catch_         => $this->processCatch($node, $scope),
            $node instanceof Stmt\Global_        => $this->processGlobal($node, $scope),
            $node instanceof Stmt\Static_        => $this->processStaticVars($node, $scope),

            $node instanceof Expr\Isset_,
            $node instanceof Expr\Empty_         => $scope,
            $node instanceof Expr\BinaryOp\Coalesce => $this->processCoalesce($node, $scope),

            $node instanceof Expr\Variable       => $scope,

            default => $this->processChildren($node, $scope),
        };
    }

    private function processChildren(Node $node, Scope $scope): Scope
    {
        foreach ($node->getSubNodeNames() as $name) {
            $scope = $this->processSubNode($node->$name, $scope);
        }

        return $scope;
    }

    private function processSubNode(mixed $subNode, Scope $scope): Scope
    {
        if ($subNode instanceof Node) {
            return $this->processNode($subNode, $scope);
        }

        if (is_array($subNode)) {
            foreach ($subNode as $item) {
                $scope = $this->processSubNode($item, $scope);
            }
        }

        return $scope;
    }

    // --- 代入: 右辺の型を推論して変数に結びつける ---

    private function processAssign(Expr\Assign|Expr\AssignRef $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->expr, $scope);
        $type = $scope->getType($node->expr);

        return $this->processAssignTarget($node->var, $type, $scope);
    }

    private function processAssignOp(Expr\AssignOp $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->expr, $scope);

        // 複合代入（+= 等）の結果型は後章で精密化。ここでは mixed に縮退して安全側に。
        return $this->processAssignTarget($node->var, new MixedType(), $scope);
    }

    private function processAssignTarget(Expr $target, Type $type, Scope $scope): Scope
    {
        return match (true) {
            $target instanceof Expr\Variable => is_string($target->name)
                ? $scope->assignVariable($target->name, $type)
                : $this->processNode($target, $scope),

            $target instanceof Expr\List_,
            $target instanceof Expr\Array_ => $this->processListAssign($target, $scope),

            $target instanceof Expr\ArrayDimFetch => $this->processArrayDimAssign($target, $scope),

            default => $this->processNode($target, $scope),
        };
    }

    private function processListAssign(Expr\List_|Expr\Array_ $node, Scope $scope): Scope
    {
        foreach ($node->items as $item) {
            if ($item === null) {
                continue;
            }

            if ($item->key !== null) {
                $scope = $this->processNode($item->key, $scope);
            }

            // 分割代入の要素型はまだ追えない → mixed
            $scope = $this->processAssignTarget($item->value, new MixedType(), $scope);
        }

        return $scope;
    }

    private function processArrayDimAssign(Expr\ArrayDimFetch $node, Scope $scope): Scope
    {
        if ($node->dim !== null) {
            $scope = $this->processNode($node->dim, $scope);
        }

        // $arr[...] = ... で $arr は配列になるが、配列型は後章。ここでは mixed。
        return $this->processAssignTarget($node->var, new MixedType(), $scope);
    }

    // --- ループ・例外・宣言 ---

    private function processForeach(Stmt\Foreach_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->expr, $scope);

        $loopScope = $scope;
        if ($node->keyVar !== null) {
            $loopScope = $this->processAssignTarget($node->keyVar, new MixedType(), $loopScope);
        }
        $loopScope = $this->processAssignTarget($node->valueVar, new MixedType(), $loopScope);

        $bodyScope = $this->processStmts($node->stmts, $loopScope);

        return $scope->mergeWith($bodyScope);
    }

    private function processCatch(Stmt\Catch_ $node, Scope $scope): Scope
    {
        $catchScope = $scope;
        if ($node->var !== null && is_string($node->var->name)) {
            // 例外の型は ObjectType を持つ Part 6 で精密化。
            $catchScope = $catchScope->assignVariable($node->var->name, new MixedType());
        }

        $bodyScope = $this->processStmts($node->stmts, $catchScope);

        return $scope->mergeWith($bodyScope);
    }

    private function processGlobal(Stmt\Global_ $node, Scope $scope): Scope
    {
        foreach ($node->vars as $var) {
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $scope = $scope->assignVariable($var->name, new MixedType());
            }
        }

        return $scope;
    }

    private function processStaticVars(Stmt\Static_ $node, Scope $scope): Scope
    {
        foreach ($node->vars as $staticVar) {
            if (is_string($staticVar->var->name)) {
                $scope = $scope->assignVariable($staticVar->var->name, new MixedType());
            }
        }

        return $scope;
    }

    private function processCoalesce(Expr\BinaryOp\Coalesce $node, Scope $scope): Scope
    {
        $scope = $this->processSilently($node->left, $scope);

        return $this->processNode($node->right, $scope);
    }

    private function processSilently(Node $node, Scope $scope): Scope
    {
        if ($node instanceof Expr\Variable) {
            return $scope;
        }

        foreach ($node->getSubNodeNames() as $name) {
            $sub = $node->$name;
            if ($sub instanceof Node) {
                $scope = $this->processSilently($sub, $scope);
            } elseif (is_array($sub)) {
                foreach ($sub as $item) {
                    if ($item instanceof Node) {
                        $scope = $this->processSilently($item, $scope);
                    }
                }
            }
        }

        return $scope;
    }

    // --- 関数・クロージャ（スコープ境界）---

    private function processFunctionLike(Stmt\Function_|Stmt\ClassMethod $node, Scope $outer): Scope
    {
        $inner = Scope::createForFunction();
        $inner = $this->bindParams($node->params, $inner);

        if ($node instanceof Stmt\ClassMethod && !$node->isStatic()) {
            $inner = $inner->assignVariable('this', new MixedType());
        }

        if ($node->stmts !== null) {
            $this->processStmts($node->stmts, $inner);
        }

        return $outer;
    }

    private function processClosure(Expr\Closure $node, Scope $outer): Scope
    {
        $inner = Scope::createForFunction();
        $inner = $this->bindParams($node->params, $inner);

        foreach ($node->uses as $use) {
            $name = is_string($use->var->name) ? $use->var->name : null;

            if ($use->byRef) {
                if ($name !== null) {
                    $outer = $outer->assignVariable($name, new MixedType());
                }
            } else {
                ($this->nodeCallback)($use->var, $outer); // 値渡し use は外側の読み取り
            }

            if ($name !== null) {
                $inner = $inner->assignVariable(
                    $name,
                    $use->byRef ? new MixedType() : $outer->getVariableType($name),
                );
            }
        }

        if (!$node->static) {
            $inner = $inner->assignVariable('this', new MixedType());
        }

        $this->processStmts($node->stmts, $inner);

        return $outer;
    }

    private function processArrowFunction(Expr\ArrowFunction $node, Scope $outer): Scope
    {
        $inner = $this->bindParams($node->params, $outer); // 外側を値で自動キャプチャ

        if (!$node->static) {
            $inner = $inner->assignVariable('this', new MixedType());
        }

        $this->processNode($node->expr, $inner);

        return $outer;
    }

    /**
     * @param Node\Param[] $params
     */
    private function bindParams(array $params, Scope $scope): Scope
    {
        foreach ($params as $param) {
            ($this->nodeCallback)($param, $scope);

            if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
                $scope = $scope->assignVariable($param->var->name, $this->typeFromHint($param->type));
            }
        }

        return $scope;
    }

    /**
     * パラメータの型宣言を {@see Type} に写す最小版。クラス型・nullable・union は
     * リフレクションと PHPDoc を扱う Part 6〜7 で精密化する。
     */
    private function typeFromHint(?Node $node): Type
    {
        if ($node instanceof Node\Identifier) {
            return match ($node->toLowerString()) {
                'int' => new IntegerType(),
                'string' => new StringType(),
                'float' => new FloatType(),
                'bool' => new BooleanType(),
                'null' => new NullType(),
                default => new MixedType(),
            };
        }

        return new MixedType();
    }
}
