<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Rules\RuleRegistry;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Walks down the AST, carrying a {@see Scope} along, and applies rules at each node.
 *
 * The core corresponding to PHPStan's {@see \PHPStan\Analyser\NodeScopeResolver}.
 * Part 1's RuleApplyingVisitor (which only visited nodes) is grown here into a
 * recursive descent that propagates a scope.
 *
 * The crux of the design is to "distinguish between read and write context". The
 * `$x` on the left-hand side of `$x = 1` is a **definition**, not a target of the
 * undefined-variable check. The `$v` in `foreach (... as $v)` is the same. We handle
 * only such binding constructs individually, and for everything else descend
 * mechanically into the children via {@see processChildren()}. A Variable we meet
 * once we have descended is a "read", so it is subject to rules.
 *
 * Control constructs (if/while/for/try...) have no dedicated handling; they merely
 * walk their children in order. Assignments propagate forward, and merges become the
 * optimistic union of {@see Scope::mergeWith()}, so no false positives are emitted.
 * Path-sensitive refinement ("is it defined on every path?") is covered in Part 5.
 */
final class NodeScopeResolver
{
    /** @var list<Error> */
    private array $errors = [];

    public function __construct(
        private readonly RuleRegistry $registry,
        private readonly string $file,
    ) {
    }

    /**
     * @param Node[] $stmts
     *
     * @return list<Error>
     */
    public function analyse(array $stmts, Scope $scope): array
    {
        $this->errors = [];
        $this->processStmts($stmts, $scope);

        return $this->errors;
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
        $this->applyRules($node, $scope);

        return match (true) {
            // --- constructs that create a scope boundary ---
            $node instanceof Stmt\Function_,
            $node instanceof Stmt\ClassMethod    => $this->processFunctionLike($node, $scope),
            $node instanceof Expr\Closure        => $this->processClosure($node, $scope),
            $node instanceof Expr\ArrowFunction  => $this->processArrowFunction($node, $scope),

            // --- constructs that bind variables ---
            $node instanceof Expr\Assign,
            $node instanceof Expr\AssignRef      => $this->processAssign($node, $scope),
            $node instanceof Expr\AssignOp       => $this->processAssignOp($node, $scope),
            $node instanceof Stmt\Foreach_       => $this->processForeach($node, $scope),
            $node instanceof Stmt\Catch_         => $this->processCatch($node, $scope),
            $node instanceof Stmt\Global_        => $this->processGlobal($node, $scope),
            $node instanceof Stmt\Static_        => $this->processStaticVars($node, $scope),

            // --- read context that tolerates undefined variables ---
            // isset($x) / empty($x) is legal even when undefined. We do not run the undefined check inside.
            $node instanceof Expr\Isset_,
            $node instanceof Expr\Empty_         => $scope,
            $node instanceof Expr\BinaryOp\Coalesce => $this->processCoalesce($node, $scope),

            // --- the variable itself (a read). Rules already applied, no children ---
            $node instanceof Expr\Variable       => $scope,

            // --- everything else descends into children, propagating the scope in order ---
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

    // --- assignment ---

    private function processAssign(Expr\Assign|Expr\AssignRef $node, Scope $scope): Scope
    {
        // Analyse the right-hand side first (a variable on the right is a read, so it is subject to the undefined check).
        $scope = $this->processNode($node->expr, $scope);

        return $this->processAssignTarget($node->var, $scope);
    }

    private function processAssignOp(Expr\AssignOp $node, Scope $scope): Scope
    {
        // A compound assignment (+= etc.) target also reads, but here we treat it as a definition
        // to avoid false positives (precise detection of "+= on an undefined variable" comes in a later chapter).
        $scope = $this->processNode($node->expr, $scope);

        return $this->processAssignTarget($node->var, $scope);
    }

    /**
     * Processes the **left-hand side** of an assignment and adds the bound variable
     * to the scope. A Variable on the left-hand side is a "definition", so it is not
     * subject to the undefined check.
     */
    private function processAssignTarget(Expr $target, Scope $scope): Scope
    {
        return match (true) {
            $target instanceof Expr\Variable => is_string($target->name)
                ? $scope->assignVariable($target->name)
                : $this->processNode($target, $scope),

            // destructuring assignment: [$a, $b] = ... / list($a, $b) = ...
            $target instanceof Expr\List_,
            $target instanceof Expr\Array_ => $this->processListAssign($target, $scope),

            // $arr[$i] = ... / $arr[] = ... : the index is a read, the root variable is a definition (auto-created)
            $target instanceof Expr\ArrayDimFetch => $this->processArrayDimAssign($target, $scope),

            // $obj->p = ... / Foo::$p = ... : the object side is a read. No new variable is born
            default => $this->processNode($target, $scope),
        };
    }

    private function processListAssign(Expr\List_|Expr\Array_ $node, Scope $scope): Scope
    {
        foreach ($node->items as $item) {
            if ($item === null) {
                continue; // a hole in [, $b] = ...
            }

            if ($item->key !== null) {
                $scope = $this->processNode($item->key, $scope); // the key is a read
            }

            $scope = $this->processAssignTarget($item->value, $scope);
        }

        return $scope;
    }

    private function processArrayDimAssign(Expr\ArrayDimFetch $node, Scope $scope): Scope
    {
        if ($node->dim !== null) {
            $scope = $this->processNode($node->dim, $scope); // the index is a read
        }

        return $this->processAssignTarget($node->var, $scope); // the root is a definition
    }

    // --- loops, exceptions, declarations ---

    private function processForeach(Stmt\Foreach_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->expr, $scope); // the iterated expression is a read

        $loopScope = $scope;
        if ($node->keyVar !== null) {
            $loopScope = $this->processAssignTarget($node->keyVar, $loopScope);
        }
        $loopScope = $this->processAssignTarget($node->valueVar, $loopScope);

        $bodyScope = $this->processStmts($node->stmts, $loopScope);

        // The loop may run zero times. Merge optimistically to avoid false positives (the loop variables remain).
        return $scope->mergeWith($bodyScope);
    }

    private function processCatch(Stmt\Catch_ $node, Scope $scope): Scope
    {
        $catchScope = $scope;
        if ($node->var !== null && is_string($node->var->name)) {
            $catchScope = $catchScope->assignVariable($node->var->name);
        }

        $bodyScope = $this->processStmts($node->stmts, $catchScope);

        return $scope->mergeWith($bodyScope);
    }

    private function processGlobal(Stmt\Global_ $node, Scope $scope): Scope
    {
        foreach ($node->vars as $var) {
            if ($var instanceof Expr\Variable && is_string($var->name)) {
                $scope = $scope->assignVariable($var->name);
            }
        }

        return $scope;
    }

    private function processStaticVars(Stmt\Static_ $node, Scope $scope): Scope
    {
        foreach ($node->vars as $staticVar) {
            if (is_string($staticVar->var->name)) {
                $scope = $scope->assignVariable($staticVar->var->name);
            }
        }

        return $scope;
    }

    private function processCoalesce(Expr\BinaryOp\Coalesce $node, Scope $scope): Scope
    {
        // `$x ?? d` is safe even when the left-hand side is undefined (short-circuit). We walk the left-hand side without the undefined check.
        $scope = $this->processSilently($node->left, $scope);

        return $this->processNode($node->right, $scope);
    }

    /**
     * Propagates the scope while not subjecting the Variable reads beneath it to the
     * undefined check. Used in the lenient context equivalent to isset().
     */
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
            $inner = $inner->assignVariable('this');
        }

        if ($node->stmts !== null) { // null for abstract methods and interfaces
            $this->processStmts($node->stmts, $inner);
        }

        return $outer; // a function definition does not add variables to the outer scope
    }

    private function processClosure(Expr\Closure $node, Scope $outer): Scope
    {
        $inner = Scope::createForFunction();
        $inner = $this->bindParams($node->params, $inner);

        foreach ($node->uses as $use) {
            // A by-value use($x) is a read from the outer scope. One with & creates a variable in the outer scope.
            if ($use->byRef) {
                if (is_string($use->var->name)) {
                    $outer = $outer->assignVariable($use->var->name);
                }
            } else {
                $this->applyRules($use->var, $outer);
            }

            if (is_string($use->var->name)) {
                $inner = $inner->assignVariable($use->var->name);
            }
        }

        if (!$node->static) {
            $inner = $inner->assignVariable('this');
        }

        $this->processStmts($node->stmts, $inner);

        return $outer;
    }

    private function processArrowFunction(Expr\ArrowFunction $node, Scope $outer): Scope
    {
        // An arrow function captures the outer scope automatically by value.
        $inner = $this->bindParams($node->params, $outer);

        if (!$node->static) {
            $inner = $inner->assignVariable('this');
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
            $this->applyRules($param, $scope);

            if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
                $scope = $scope->assignVariable($param->var->name);
            }
        }

        return $scope;
    }

    private function applyRules(Node $node, Scope $scope): void
    {
        foreach ($this->registry->getRulesFor($node) as $rule) {
            foreach ($rule->processNode($node, $scope) as $ruleError) {
                $this->errors[] = new Error($ruleError->message, $this->file, $ruleError->line);
            }
        }
    }
}
