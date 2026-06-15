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
 * Walks down the AST, carrying a {@see Scope} along, and invokes a **callback**
 * at each node.
 *
 * The core corresponding to PHPStan's {@see \PHPStan\Analyser\NodeScopeResolver}.
 * In Part 2 it held rule application directly, but Part 4 generalizes it into a
 * generic callback that is "called with (node, scope) at each node". This lets the
 * rule-running analysis ({@see Analyser}) and the `annotate` that peeks at inferred
 * types both reuse the same traversal.
 *
 * The crux of the traversal is the same "distinction between read and write
 * context" as in Part 2. On top of that, Part 4 infers the right-hand side's type
 * with {@see Scope::getType()} on every assignment and binds it to the variable.
 */
final class NodeScopeResolver
{
    /** @var Closure(Node, Scope): void */
    private Closure $nodeCallback;

    private TypeSpecifier $typeSpecifier;

    /**
     * @param callable(Node, Scope): void $nodeCallback
     */
    public function __construct(callable $nodeCallback)
    {
        $this->nodeCallback = Closure::fromCallable($nodeCallback);
        $this->typeSpecifier = new TypeSpecifier();
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

    // --- assignment: infer the right-hand side's type and bind it to the variable ---

    private function processAssign(Expr\Assign|Expr\AssignRef $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->expr, $scope);
        $type = $scope->getType($node->expr);

        return $this->processAssignTarget($node->var, $type, $scope);
    }

    private function processAssignOp(Expr\AssignOp $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->expr, $scope);

        // The result type of a compound assignment (+=, etc.) is refined in a later chapter. For now collapse to mixed to stay on the safe side.
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

            // The element type of a destructuring assignment cannot be tracked yet -> mixed
            $scope = $this->processAssignTarget($item->value, new MixedType(), $scope);
        }

        return $scope;
    }

    private function processArrayDimAssign(Expr\ArrayDimFetch $node, Scope $scope): Scope
    {
        if ($node->dim !== null) {
            $scope = $this->processNode($node->dim, $scope);
        }

        // $arr[...] = ... makes $arr an array, but array types come in a later chapter. For now mixed.
        return $this->processAssignTarget($node->var, new MixedType(), $scope);
    }

    // --- conditional branches: apply type narrowing ---

    private function processIf(Stmt\If_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->cond, $scope);
        $specified = $this->typeSpecifier->specify($node->cond, $scope);

        $endScopes = [];
        $endScopes[] = $this->processStmts($node->stmts, $specified->truthy);

        // Carry the world line where every preceding condition was false while walking the elseifs.
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
            $endScopes[] = $falsy; // with no else, the "all conditions false" path simply continues
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

        // As in `$x ? $x->foo() : null`, each branch is analysed in its narrowed scope.
        // This is also why $y in `isset($y) ? $y : d` is defined on the true branch.
        if ($node->if !== null) {
            $this->processNode($node->if, $specified->truthy);
        }
        $this->processNode($node->else, $specified->falsy);

        return $scope;
    }

    // --- loops, exceptions, declarations ---

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
            // The exception's type is refined in Part 6, once we have ObjectType.
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

    // --- functions and closures (scope boundaries) ---

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
                ($this->nodeCallback)($use->var, $outer); // a by-value use reads from the outer scope
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
        $inner = $this->bindParams($node->params, $outer); // captures the outer scope automatically by value

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
     * A minimal version that maps a parameter's type declaration onto a {@see Type}.
     * Class types, nullable, and union types are refined in Parts 6-7, which handle
     * reflection and PHPDoc.
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
