<?php

declare(strict_types=1);

namespace Ministan\Analyser;

use Ministan\Rules\RuleRegistry;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * AST を下りながら {@see Scope} を運び、各ノードでルールを適用する。
 *
 * PHPStan の {@see \PHPStan\Analyser\NodeScopeResolver} に対応する核。
 * Part 1 の RuleApplyingVisitor（ノードを訪れるだけ）を、ここで「スコープを
 * 伝播させる再帰下降」へと育てた。
 *
 * 設計の要は「読み取り文脈と書き込み文脈を区別する」こと。`$x = 1` の左辺 `$x` は
 * **定義**であって未定義チェックの対象ではない。`foreach (... as $v)` の `$v` も同様。
 * そうした束縛構文だけを個別に捌き、それ以外は {@see processChildren()} で機械的に
 * 子へ降りる。降りた先で出会う Variable は「読み取り」なのでルールに掛かる。
 *
 * 制御構文（if/while/for/try…）は専用処理を持たず、子を順に辿るだけにしている。
 * 代入は前方へ伝播し、合流は {@see Scope::mergeWith()} の楽観的和集合になるため、
 * 偽陽性を出さない。経路に敏感な精密化（「全経路で定義されたか」）は Part 5 で扱う。
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
            // --- スコープ境界を作る構文 ---
            $node instanceof Stmt\Function_,
            $node instanceof Stmt\ClassMethod    => $this->processFunctionLike($node, $scope),
            $node instanceof Expr\Closure        => $this->processClosure($node, $scope),
            $node instanceof Expr\ArrowFunction  => $this->processArrowFunction($node, $scope),

            // --- 変数を束縛する構文 ---
            $node instanceof Expr\Assign,
            $node instanceof Expr\AssignRef      => $this->processAssign($node, $scope),
            $node instanceof Expr\AssignOp       => $this->processAssignOp($node, $scope),
            $node instanceof Stmt\Foreach_       => $this->processForeach($node, $scope),
            $node instanceof Stmt\Catch_         => $this->processCatch($node, $scope),
            $node instanceof Stmt\Global_        => $this->processGlobal($node, $scope),
            $node instanceof Stmt\Static_        => $this->processStaticVars($node, $scope),

            // --- 未定義変数を許容する読み取り文脈 ---
            // isset($x) / empty($x) は未定義でも合法。中は未定義チェックしない。
            $node instanceof Expr\Isset_,
            $node instanceof Expr\Empty_         => $scope,
            $node instanceof Expr\BinaryOp\Coalesce => $this->processCoalesce($node, $scope),

            // --- 変数そのもの（読み取り）。ルール適用済み、子はない ---
            $node instanceof Expr\Variable       => $scope,

            // --- それ以外は子へ降り、スコープを順に伝播 ---
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

    // --- 代入 ---

    private function processAssign(Expr\Assign|Expr\AssignRef $node, Scope $scope): Scope
    {
        // 右辺を先に解析する（右辺の変数は読み取りなので未定義チェックの対象）。
        $scope = $this->processNode($node->expr, $scope);

        return $this->processAssignTarget($node->var, $scope);
    }

    private function processAssignOp(Expr\AssignOp $node, Scope $scope): Scope
    {
        // 複合代入（+= など）の対象は読みも兼ねるが、ここでは定義として扱い
        // 偽陽性を避ける（厳密な「未定義への +=」検出は後の章へ）。
        $scope = $this->processNode($node->expr, $scope);

        return $this->processAssignTarget($node->var, $scope);
    }

    /**
     * 代入の**左辺**を処理し、束縛された変数をスコープに加える。
     * 左辺の Variable は「定義」なので未定義チェックには掛けない。
     */
    private function processAssignTarget(Expr $target, Scope $scope): Scope
    {
        return match (true) {
            $target instanceof Expr\Variable => is_string($target->name)
                ? $scope->assignVariable($target->name)
                : $this->processNode($target, $scope),

            // 分割代入: [$a, $b] = ... / list($a, $b) = ...
            $target instanceof Expr\List_,
            $target instanceof Expr\Array_ => $this->processListAssign($target, $scope),

            // $arr[$i] = ... / $arr[] = ... : 添字は読み取り、根の変数は定義（自動生成）
            $target instanceof Expr\ArrayDimFetch => $this->processArrayDimAssign($target, $scope),

            // $obj->p = ... / Foo::$p = ... : オブジェクト側は読み取り。新しい変数は生まれない
            default => $this->processNode($target, $scope),
        };
    }

    private function processListAssign(Expr\List_|Expr\Array_ $node, Scope $scope): Scope
    {
        foreach ($node->items as $item) {
            if ($item === null) {
                continue; // [, $b] = ... の空き
            }

            if ($item->key !== null) {
                $scope = $this->processNode($item->key, $scope); // キーは読み取り
            }

            $scope = $this->processAssignTarget($item->value, $scope);
        }

        return $scope;
    }

    private function processArrayDimAssign(Expr\ArrayDimFetch $node, Scope $scope): Scope
    {
        if ($node->dim !== null) {
            $scope = $this->processNode($node->dim, $scope); // 添字は読み取り
        }

        return $this->processAssignTarget($node->var, $scope); // 根を定義
    }

    // --- ループ・例外・宣言 ---

    private function processForeach(Stmt\Foreach_ $node, Scope $scope): Scope
    {
        $scope = $this->processNode($node->expr, $scope); // 反復対象は読み取り

        $loopScope = $scope;
        if ($node->keyVar !== null) {
            $loopScope = $this->processAssignTarget($node->keyVar, $loopScope);
        }
        $loopScope = $this->processAssignTarget($node->valueVar, $loopScope);

        $bodyScope = $this->processStmts($node->stmts, $loopScope);

        // ループは 0 回かもしれない。楽観的に合流して偽陽性を避ける（ループ変数は残る）。
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
        // `$x ?? d` は左辺が未定義でも安全（短絡）。左辺は未定義チェックせず辿る。
        $scope = $this->processSilently($node->left, $scope);

        return $this->processNode($node->right, $scope);
    }

    /**
     * スコープは伝播させつつ、配下の Variable 読み取りを未定義チェックに掛けない。
     * isset() 相当の寛容な文脈で使う。
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

    // --- 関数・クロージャ（スコープ境界）---

    private function processFunctionLike(Stmt\Function_|Stmt\ClassMethod $node, Scope $outer): Scope
    {
        $inner = Scope::createForFunction();
        $inner = $this->bindParams($node->params, $inner);

        if ($node instanceof Stmt\ClassMethod && !$node->isStatic()) {
            $inner = $inner->assignVariable('this');
        }

        if ($node->stmts !== null) { // 抽象メソッド・インターフェイスは null
            $this->processStmts($node->stmts, $inner);
        }

        return $outer; // 関数定義は外側の変数を増やさない
    }

    private function processClosure(Expr\Closure $node, Scope $outer): Scope
    {
        $inner = Scope::createForFunction();
        $inner = $this->bindParams($node->params, $inner);

        foreach ($node->uses as $use) {
            // 値渡しの use($x) は外側の読み取り。&付きは外側に変数を生む。
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
        // アロー関数は外側を値で自動キャプチャする。
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
