# Part 1 — PHP-Parser と AST

> ＊この章のコードはスナップショット [`impls/01-php-parser`](../../impls/01-php-parser) にあります（この章の到達点は `git tag part-01`）。

Part 0 では入口（パース）と出口（報告）を通しました。本章ではその **間** に
最初の検査を差し込みます。題材は、誰もが一度はやらかす **`var_dump()` の消し忘れ** です。

## AST という土俵

静的解析はコードを文字列としてではなく、**構造（木）** として扱います。
nikic/php-parser はソースを **抽象構文木（AST）** に変換してくれます。

```php
var_dump($value);
```

はおおよそ次の木になります:

```
Stmt\Expression
└─ Expr\FuncCall
   ├─ name: Name("var_dump")
   └─ args: [ Arg(Expr\Variable("value")) ]
```

私たちのルールは「`Expr\FuncCall` で、名前が `var_dump` のもの」を探せばよい。
正規表現で `var_dump` を grep するのと違い、AST なら **コメントや文字列中の
`var_dump`** に誤反応しません。これが構文解析の上に立つ価値です。

## `Rule` インターフェイス

PHPStan の心臓は `Rule` です。ministan でも同じ形を最小化して導入します
（[`src/Rules/Rule.php`](../../impls/01-php-parser/src/Rules/Rule.php)）:

```php
/** @template TNodeType of Node */
interface Rule
{
    /** @return class-string<TNodeType> */
    public function getNodeType(): string;

    /**
     * @param TNodeType $node
     * @return list<RuleError>
     */
    public function processNode(Node $node): array;
}
```

- `getNodeType()` … 「どのノードに反応するか」をクラス名で宣言する
- `processNode()` … そのノードを受け取り、問題があれば `RuleError` を返す

> このあと Part 2 で、`processNode()` には「その地点で何が分かっているか」を表す
> `Scope` 引数が加わります（`processNode(Node $node, Scope $scope)`）。各章でルールは少しずつ
> 賢くなります。

`@template` は **PHPDoc で型変数を宣言する**記法です（型変数の本格的な話は応用編で扱うので、
ここは「`getNodeType()` が返す型 = `processNode()` が受け取る型」を結ぶ印、くらいで読み流して
構いません）。PHP の構文を変えずに docblock で型を持たせるこの擬似ジェネリクスは、もともと
Hack に源流を持ち PHP では Psalm が先駆け、PHPStan も含め日常的に使われます。私たちが目指す
静的解析器が最終的に検証できるようになる種類のコードでもあります。

## 最初のルール

[`NoVarDumpRule`](../../impls/01-php-parser/src/Rules/Functions/NoVarDumpRule.php) は型を一切使いません。
純粋な構文パターンマッチです:

```php
/** @implements Rule<FuncCall> */
final class NoVarDumpRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node): array
    {
        assert($node instanceof FuncCall);

        // $callback() のような動的呼び出しは名前が静的に分からない → 対象外
        if (!$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'var_dump') {
            return [];
        }

        return [new RuleError('Called var_dump().', $node->getStartLine())];
    }
}
```

ここで早くも **non-rejecting** の哲学が顔を出します。`$node->name` が `Name` でない
（＝動的呼び出し）とき、私たちは **黙って見送ります**。「もしかしたら var_dump かも」と
騒ぎ立てない。確信が持てる構文だけを叩く。

> 名前空間解決もまだしません。`namespace Foo; var_dump()` がグローバル関数に
> フォールバックするか否かは、関数の存在を知って初めて判断できます。それは
> リフレクションを導入する Part 6 の仕事です。

## ルールを束ねる — `RuleRegistry`

ノードは何千個もあり、ルールも増えていきます。全ノード×全ルールを総当たりするのは
無駄なので、**ノード種別で索引**します（[`RuleRegistry`](../../impls/01-php-parser/src/Rules/RuleRegistry.php)）。

引き当てのとき、ノード自身のクラスだけでなく **親クラスと実装インターフェイス** も
たどるのがミソです:

```php
private function classHierarchy(Node $node): array
{
    $class = $node::class;

    return [
        $class,
        ...array_values(class_parents($class)),
        ...array_values(class_implements($class)),
    ];
}
```

おかげで、具象 `Expr\FuncCall` を狙うルールも、抽象 `Expr` を狙うルールも、
同じ仕組みで共存できます。PHPStan の `Registry` もこの戦略です。

## AST を歩く — Visitor

最後に、AST を 1 ノードずつ訪ねてルールを当てる係が要ります。php-parser の
`NodeVisitor` に乗ります（[`RuleApplyingVisitor`](../../impls/01-php-parser/src/Analyser/RuleApplyingVisitor.php)）:

```php
public function enterNode(Node $node): null
{
    foreach ($this->registry->getRulesFor($node) as $rule) {
        foreach ($rule->processNode($node) as $ruleError) {
            $this->errors[] = new Error($ruleError->message, $this->file, $ruleError->line);
        }
    }

    return null;
}
```

`Analyser` はパース結果をこの Visitor に流し、集まった `Error` を返すだけになりました:

```php
$visitor = new RuleApplyingVisitor($this->registry, $file);
(new NodeTraverser($visitor))->traverse($ast);

return $visitor->getErrors();
```

> この `RuleApplyingVisitor` こそ、PHPStan で言う `NodeScopeResolver` の幼生です。
> いまは「ノードを訪れてルールを当てる」だけですが、Part 2 で木を下りながら
> **`Scope`（各地点での変数の型）** を運ぶように育てます。

## 動かす

```console
$ dev/bin/ministan analyse examples/with-var-dump.php
 examples/with-var-dump.php:12
   Called var_dump().

 [ERROR] Found 1 error
```

コメントや文字列に `var_dump` と書いても誤検出しないことを、ぜひ試してください。

## まとめ

- 解析は文字列ではなく **AST** に対して行う
- `Rule` は `getNodeType()`（どのノードか）と `processNode()`（何を報告するか）の 2 つ
- `RuleRegistry` がノード種別でルールを索引し、クラス階層をたどって引き当てる
- `RuleApplyingVisitor` が木を歩いてルールを適用する —— これが将来の
  スコープ解決器の出発点

次の Part 2 では、いよいよ **`Scope`** を導入します。木を下りながら「いまこの変数には
何が代入されているか」を運び、**未定義変数の使用** を検出します。これが PHPStan の
level 0 の核であり、型推論への入口です。
