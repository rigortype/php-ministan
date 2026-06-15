<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Closure;
use Ministan\Reflection\PhpDocTypeResolver;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\Reflection\TypeNodeResolver;
use Ministan\Type\ArrayType;
use Ministan\Type\Constant\ConstantArrayType;
use Ministan\Type\MixedType;
use Ministan\Type\ObjectType;
use Ministan\Type\Type;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
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
            $node instanceof Expr\Match_         => $this->processMatch($node, $scope),
            $node instanceof Expr\Ternary        => $this->processTernary($node, $scope),
            $node instanceof Stmt\Foreach_       => $this->processForeach($node, $scope),
            $node instanceof Stmt\While_         => $this->processWhile($node, $scope),
            $node instanceof Stmt\Catch_         => $this->processCatch($node, $scope),
            $node instanceof Stmt\Global_        => $this->processGlobal($node, $scope),
            $node instanceof Stmt\Static_        => $this->processStaticVars($node, $scope),

            $node instanceof Expr\FuncCall       => $this->processFuncCall($node, $scope),
            $node instanceof Expr\MethodCall     => $this->processMethodCall($node, $scope),

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

    // --- statements and @var ---

    private function processExpressionStmt(Stmt\Expression $node, Scope $scope): Scope
    {
        $expr = $node->expr;

        // assert($x instanceof Foo) narrows $x in the scope that follows.
        if ($expr instanceof Expr\FuncCall
            && $expr->name instanceof Name
            && $expr->name->toLowerString() === 'assert'
            && ($expr->args[0] ?? null) instanceof Arg
        ) {
            $this->processNode($expr, $scope);

            return $this->typeSpecifier->specify($expr->args[0]->value, $scope)->truthy;
        }

        $doc = $node->getDocComment();

        if ($doc !== null
            && $expr instanceof Expr\Assign
            && $expr->var instanceof Expr\Variable
            && is_string($expr->var->name)
        ) {
            $parsed = $this->phpDocTypeResolver->parse($doc->getText());
            $varType = $parsed->varTypes[$expr->var->name] ?? $parsed->varTypes[''] ?? null;

            if ($varType !== null) {
                // Process the assignment as usual (apply rules, read the right-hand side), then override the type with @var.
                $scope = $this->processNode($expr, $scope);

                return $scope->assignVariable($expr->var->name, $varType);
            }
        }

        return $this->processChildren($node, $scope);
    }

    /**
     * The right-hand side of `&&`/`||` is evaluated in the world narrowed by the
     * left-hand side. This is why $x becomes non-null on the right-hand side of
     * `$x !== null && $x->foo()`.
     */
    private function processLogical(Expr\BinaryOp\BooleanAnd|Expr\BinaryOp\BooleanOr $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->left, $scope);
        $specified = $this->typeSpecifier->specify($node->left, $scope);

        // && evaluates the right-hand side in the "left is true" world line, || in the "left is false" one.
        $rightScope = $node instanceof Expr\BinaryOp\BooleanAnd ? $specified->truthy : $specified->falsy;
        $this->processNode($node->right, $rightScope);

        return $scope;
    }

    // --- conditional branches: apply type narrowing ---

    private function processIf(Stmt\If_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->cond, $scope);
        $specified = $this->typeSpecifier->specify($node->cond, $scope);

        // A branch that ends in return / throw does not contribute to the merge. This is what produces early-return narrowing:
        // after passing `if ($x === null) { return; }`, $x continues as non-null.
        $endScopes = [];
        $thenScope = $this->processStmts($node->stmts, $specified->truthy);
        if (!$this->alwaysTerminates($node->stmts)) {
            $endScopes[] = $thenScope;
        }

        $falsy = $specified->falsy;
        foreach ($node->elseifs as $elseif) {
            $falsy = $this->processNode($elseif->cond, $falsy);
            $branch = $this->typeSpecifier->specify($elseif->cond, $falsy);
            $branchScope = $this->processStmts($elseif->stmts, $branch->truthy);
            if (!$this->alwaysTerminates($elseif->stmts)) {
                $endScopes[] = $branchScope;
            }
            $falsy = $branch->falsy;
        }

        if ($node->else !== null) {
            $elseScope = $this->processStmts($node->else->stmts, $falsy);
            if (!$this->alwaysTerminates($node->else->stmts)) {
                $endScopes[] = $elseScope;
            }
        } else {
            $endScopes[] = $falsy; // with no else, the "all conditions false" path simply continues
        }

        if ($endScopes === []) {
            return $falsy; // every path terminates
        }

        $result = array_shift($endScopes);
        foreach ($endScopes as $branchScope) {
            $result = $result->mergeWith($branchScope);
        }

        return $result;
    }

    /**
     * Whether a sequence of statements always ends in return / throw / exit (i.e.
     * the following code is unreachable).
     *
     * @param Node[] $stmts
     */
    private function alwaysTerminates(array $stmts): bool
    {
        if ($stmts === []) {
            return false;
        }

        $last = $stmts[array_key_last($stmts)];

        if ($last instanceof Stmt\Return_) {
            return true;
        }
        if ($last instanceof Stmt\Expression && $last->expr instanceof Expr\Throw_) {
            return true;
        }
        if ($last instanceof Stmt\Expression && $last->expr instanceof Expr\Exit_) {
            return true;
        }

        return false;
    }

    /**
     * Analyses each arm of a match in its narrowed scope. This is where we pay off
     * the "narrowing in match arms" left as homework in S2.
     */
    private function processMatch(Expr\Match_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->cond, $scope);

        // For `match (true)` the arm's condition expression is itself the truth condition. For `match ($x)` it is $x === the arm's value.
        $matchesTrue = $node->cond instanceof Expr\ConstFetch
            && $node->cond->name->toLowerString() === 'true';

        $remaining = $scope;
        foreach ($node->arms as $arm) {
            if ($arm->conds === null) {
                $this->processNode($arm->body, $remaining); // default
                continue;
            }

            $armScope = $remaining;
            foreach ($arm->conds as $cond) {
                $this->processNode($cond, $remaining);
                $specified = $matchesTrue
                    ? $this->typeSpecifier->specify($cond, $remaining)
                    : $this->typeSpecifier->specifyEquality($node->cond, $cond, $remaining);
                $armScope = $specified->truthy;
                $remaining = $specified->falsy;
            }

            $this->processNode($arm->body, $armScope);
        }

        return $scope;
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
        $iterableType = $scope->getType($node->expr);

        $loopScope = $scope;
        if ($node->keyVar !== null) {
            $loopScope = $this->processAssignTarget($node->keyVar, $this->iterableKeyType($iterableType), $loopScope);
        }
        $loopScope = $this->processAssignTarget($node->valueVar, $this->iterableValueType($iterableType), $loopScope);

        $bodyScope = $this->analyseLoopBody($node->stmts, $loopScope);

        return $scope->mergeWith($bodyScope);
    }

    private function processWhile(Stmt\While_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->cond, $scope);
        $specified = $this->typeSpecifier->specify($node->cond, $scope);

        $bodyScope = $this->analyseLoopBody($node->stmts, $specified->truthy);

        // After the loop: either the world line where the condition became false (falsy), or after running the body (bodyScope).
        return $specified->falsy->mergeWith($bodyScope);
    }

    /**
     * Analyses the loop body with a fixed-point approximation.
     *
     * First it walks the body **silently** (no rules fired) to obtain a scope where
     * the types have been widened by assignments that cross the loop. Then it does
     * the real analysis of the body exactly once (rules fire only on this one pass).
     * This lets the analysis account for the variable types (unions) seen from the
     * second iteration onward.
     *
     * @param Node[] $stmts
     */
    private function analyseLoopBody(array $stmts, Scope $entry): Scope
    {
        $discovered = $this->silently(fn (): Scope => $this->processStmts($stmts, $entry));
        $widened = $entry->mergeWith($discovered);

        $result = $this->processStmts($stmts, $widened);

        return $widened->mergeWith($result);
    }

    /**
     * Runs a function with the callback (rule application) temporarily disabled.
     * Used in the loop's fixed-point pass, where we only want to discover scopes.
     *
     * @param callable(): Scope $fn
     */
    private function silently(callable $fn): Scope
    {
        $saved = $this->nodeCallback;
        $this->nodeCallback = static function (): void {
        };

        try {
            return $fn();
        } finally {
            $this->nodeCallback = $saved;
        }
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

    // --- calls: a by-ref output argument "defines" the variable ---

    private function processFuncCall(Expr\FuncCall $node, Scope $scope): Scope
    {
        if (!$node->name instanceof Name) {
            $scope = $this->processNode($node->name, $scope); // dynamic call $fn()
        }

        return $this->processCallArgs($node->args, $this->byRefParamsOfFunction($node), $scope);
    }

    private function processMethodCall(Expr\MethodCall $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->var, $scope); // the receiver is read

        return $this->processCallArgs($node->args, $this->byRefParamsOfMethod($node, $scope), $scope);
    }

    /**
     * @param array<Arg|Node\VariadicPlaceholder> $args
     * @param list<bool> $byRef
     */
    private function processCallArgs(array $args, array $byRef, Scope $scope): Scope
    {
        foreach ($args as $position => $arg) {
            if (!$arg instanceof Arg) {
                continue;
            }

            if (($byRef[$position] ?? false) && $arg->value instanceof Expr\Variable && is_string($arg->value->name)) {
                // By-ref output argument: this "defines" $m in preg_match(..., $m).
                $scope = $scope->assignVariable($arg->value->name, new MixedType());
            } else {
                $scope = $this->processNode($arg->value, $scope);
            }
        }

        return $scope;
    }

    /**
     * @return list<bool>
     */
    private function byRefParamsOfFunction(Expr\FuncCall $node): array
    {
        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if ($node->name instanceof Name && $provider !== null && $provider->hasFunction($node->name->toString())) {
            return $provider->getFunction($node->name->toString())->byRefParams;
        }

        return [];
    }

    /**
     * @return list<bool>
     */
    private function byRefParamsOfMethod(Expr\MethodCall $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $objectType = $scope->getType($node->var);
        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if ($objectType instanceof ObjectType && $provider !== null && $provider->hasClass($objectType->className)) {
            $class = $provider->getClass($objectType->className);
            if ($class->hasMethod($node->name->toString())) {
                return $class->getMethod($node->name->toString())->byRefParams;
            }
        }

        return [];
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
     * @param Node\Param[]        $params
     * @param array<string, Type> $docParamTypes types declared via @param (these take precedence over the native declaration)
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
