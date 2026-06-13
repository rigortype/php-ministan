<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Closure;
use Ministan\Reflection\PhpDocTypeResolver;
use Ministan\Reflection\TypeNodeResolver;
use Ministan\Type\ArrayType;
use Ministan\Type\Constant\ConstantArrayType;
use Ministan\Type\MixedType;
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

    private TypeSpecifier $typeSpecifier;

    private TypeNodeResolver $typeNodeResolver;

    private PhpDocTypeResolver $phpDocTypeResolver;

    /**
     * @param callable(Node, Scope): void $nodeCallback
     */
    public function __construct(callable $nodeCallback)
    {
        $this->nodeCallback = Closure::fromCallable($nodeCallback);
        $this->typeSpecifier = new TypeSpecifier();
        $this->typeNodeResolver = new TypeNodeResolver();
        $this->phpDocTypeResolver = new PhpDocTypeResolver();
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
            $node instanceof Stmt\Expression     => $this->processExpressionStmt($node, $scope),
            $node instanceof Expr\BinaryOp\BooleanAnd,
            $node instanceof Expr\BinaryOp\BooleanOr => $this->processLogical($node, $scope),
            $node instanceof Stmt\If_            => $this->processIf($node, $scope),
            $node instanceof Expr\Ternary        => $this->processTernary($node, $scope),
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

    // --- 文と @var ---

    private function processExpressionStmt(Stmt\Expression $node, Scope $scope): Scope
    {
        $expr = $node->expr;
        $doc = $node->getDocComment();

        if ($doc !== null
            && $expr instanceof Expr\Assign
            && $expr->var instanceof Expr\Variable
            && is_string($expr->var->name)
        ) {
            $parsed = $this->phpDocTypeResolver->parse($doc->getText());
            $varType = $parsed->varTypes[$expr->var->name] ?? $parsed->varTypes[''] ?? null;

            if ($varType !== null) {
                // 通常どおり代入を処理（ルール適用・右辺読み取り）した上で、@var で型を上書きする。
                $scope = $this->processNode($expr, $scope);

                return $scope->assignVariable($expr->var->name, $varType);
            }
        }

        return $this->processChildren($node, $scope);
    }

    /**
     * `&&`／`||` の右辺は、左辺で絞り込まれた世界で評価される。
     * `$x !== null && $x->foo()` の右辺で $x が非 null になるのはこのため。
     */
    private function processLogical(Expr\BinaryOp\BooleanAnd|Expr\BinaryOp\BooleanOr $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->left, $scope);
        $specified = $this->typeSpecifier->specify($node->left, $scope);

        // && は「左が真」、|| は「左が偽」の世界線で右辺を評価する。
        $rightScope = $node instanceof Expr\BinaryOp\BooleanAnd ? $specified->truthy : $specified->falsy;
        $this->processNode($node->right, $rightScope);

        return $scope;
    }

    // --- 条件分岐: 型の絞り込みを適用する ---

    private function processIf(Stmt\If_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->cond, $scope);
        $specified = $this->typeSpecifier->specify($node->cond, $scope);

        $endScopes = [];
        $endScopes[] = $this->processStmts($node->stmts, $specified->truthy);

        // それまでの条件がすべて偽だった世界線を運びながら elseif を辿る。
        $falsy = $specified->falsy;
        foreach ($node->elseifs as $elseif) {
            $falsy = $this->processNode($elseif->cond, $falsy);
            $branch = $this->typeSpecifier->specify($elseif->cond, $falsy);
            $endScopes[] = $this->processStmts($elseif->stmts, $branch->truthy);
            $falsy = $branch->falsy;
        }

        if ($node->else !== null) {
            $endScopes[] = $this->processStmts($node->else->stmts, $falsy);
        } else {
            $endScopes[] = $falsy; // else が無ければ「全条件が偽」の経路がそのまま続く
        }

        $result = array_shift($endScopes);
        foreach ($endScopes as $branchScope) {
            $result = $result->mergeWith($branchScope);
        }

        return $result;
    }

    private function processTernary(Expr\Ternary $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->cond, $scope);
        $specified = $this->typeSpecifier->specify($node->cond, $scope);

        // `$x ? $x->foo() : null` のように、各枝は絞り込まれたスコープで解析する。
        // これにより `isset($y) ? $y : d` の $y も真の枝では定義済みになる。
        if ($node->if !== null) {
            $this->processNode($node->if, $specified->truthy);
        }
        $this->processNode($node->else, $specified->falsy);

        return $scope;
    }

    // --- ループ・例外・宣言 ---

    private function processForeach(Stmt\Foreach_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->expr, $scope);
        $iterableType = $scope->getType($node->expr);

        $loopScope = $scope;
        if ($node->keyVar !== null) {
            $loopScope = $this->processAssignTarget($node->keyVar, $this->iterableKeyType($iterableType), $loopScope);
        }
        $loopScope = $this->processAssignTarget($node->valueVar, $this->iterableValueType($iterableType), $loopScope);

        $bodyScope = $this->processStmts($node->stmts, $loopScope);

        return $scope->mergeWith($bodyScope);
    }

    private function iterableKeyType(Type $type): Type
    {
        if ($type instanceof ConstantArrayType) {
            return $type->getIterableKeyType();
        }
        if ($type instanceof ArrayType) {
            return $type->getIterableKeyType();
        }

        return new MixedType();
    }

    private function iterableValueType(Type $type): Type
    {
        if ($type instanceof ConstantArrayType) {
            return $type->getIterableValueType();
        }
        if ($type instanceof ArrayType) {
            return $type->getIterableValueType();
        }

        return new MixedType();
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
        $doc = $this->phpDocTypeResolver->parse($node->getDocComment()?->getText());
        $returnType = $doc->returnType ?? $this->typeNodeResolver->resolve($node->returnType);

        $inner = Scope::createForFunction()->withFunctionReturnType($returnType);
        $inner = $this->bindParams($node->params, $inner, $doc->paramTypes);

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
     * @param Node\Param[]        $params
     * @param array<string, Type> $docParamTypes @param で宣言された型（ネイティブ宣言より優先）
     */
    private function bindParams(array $params, Scope $scope, array $docParamTypes = []): Scope
    {
        foreach ($params as $param) {
            ($this->nodeCallback)($param, $scope);

            if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
                $name = $param->var->name;
                $type = $docParamTypes[$name] ?? $this->typeNodeResolver->resolve($param->type);
                $scope = $scope->assignVariable($name, $type);
            }
        }

        return $scope;
    }
}
