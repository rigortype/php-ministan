# Part 7 — PHPDoc

> ＊この章のコードはスナップショット [`impls/07-phpdoc`](../../impls/07-phpdoc) にあります（この章の到達点は `git tag part-07`）。

PHP のネイティブ型宣言には限界があります。`array` とは書けても「何の配列か」は書けない。

```php
/**
 * @param list<string> $names
 * @return array<int, string>
 */
function shout(array $names): array { /* … */ }
```

この **PHPDoc** こそ、PHP の静的解析が実用になる鍵です。本章では
`phpstan/phpdoc-parser` を導入し、`@param`／`@return`／`@var` を型として取り込みます。

## phpdoc-parser をつなぐ

型「文字列」のパースは自前で書かず、PHPStan 公式の
[phpstan/phpdoc-parser](https://github.com/phpstan/phpdoc-parser) に任せます。これが返す
型 AST を、ministan の {@see Type} へ変換するのが
[`PhpDocTypeResolver`](../../impls/07-phpdoc/src/Reflection/PhpDocTypeResolver.php) の仕事です
（PHPStan の `TypeNodeResolver` に対応）:

```php
private function toType(TypeNode $node): Type
{
    return match (true) {
        $node instanceof IdentifierTypeNode => $this->fromIdentifier($node->name),
        $node instanceof NullableTypeNode   => TypeCombinator::union($this->toType($node->type), new NullType()),
        $node instanceof UnionTypeNode      => TypeCombinator::union(...array_map($this->toType(...), $node->types)),
        $node instanceof GenericTypeNode    => $this->fromGeneric($node), // array<…>, list<…>
        $node instanceof ArrayTypeNode      => new ArrayType(new MixedType(), $this->toType($node->type)), // T[]
        default => new MixedType(),
    };
}
```

Part 5 で作った `TypeCombinator::union()` が `?int` → `int|null` でそのまま効きます。
小さな部品が積み上がっていきます。

## 配列形状の入口 —— `ArrayType`

PHPDoc から初めての**複合型**が生まれます。キーと要素の型を持つ
[`ArrayType`](../../impls/07-phpdoc/src/Type/ArrayType.php) です:

```php
final class ArrayType implements Type
{
    public function __construct(
        public readonly Type $keyType,
        public readonly Type $itemType,
    ) {}

    public function describe(): string
    {
        return sprintf('array<%s, %s>', $this->keyType->describe(), $this->itemType->describe());
    }
}
```

`array<int, string>` はそのまま、`list<string>` は `array<int, string>`、`string[]` は
`array<mixed, string>` に写します。要素型を保てることが、応用編での配列アクセス推論に
つながります。

## PHPDoc はネイティブより優先

`@param`／`@return` はリフレクションに取り込みます。鉄則は
**「PHPDoc があれば PHPDoc、無ければネイティブ宣言」**
（[`MethodReflection`](../../impls/07-phpdoc/src/Reflection/MethodReflection.php)）:

```php
$doc = $phpDoc->parse($node->getDocComment()?->getText());
// …
return new self(
    $node->name->toString(),
    $doc->returnType ?? $resolver->resolve($node->returnType), // PHPDoc 優先
    $parameterTypes,
);
```

`array` としか書けないネイティブ宣言を、`array<int, string>` という精密な PHPDoc が
上書きする。PHPStan がそうしているのと同じ理由です。

## `@var` —— その場で型を宣言する

代入文に付いた `/** @var int $count */` は、推論結果を上書きします。`NodeScopeResolver`
が代入文を処理する際に docblock を読みます
（[`processExpressionStmt`](../../impls/07-phpdoc/src/Analyser/NodeScopeResolver.php)）:

```php
$parsed = $this->phpDocTypeResolver->parse($doc->getText());
$varType = $parsed->varTypes[$expr->var->name] ?? $parsed->varTypes[''] ?? null;
if ($varType !== null) {
    $scope = $this->processNode($expr, $scope);          // 通常の代入処理
    return $scope->assignVariable($expr->var->name, $varType); // @var で上書き
}
```

## 動かす

```console
$ dev/bin/ministan annotate examples/phpdoc.php
examples/phpdoc.php
    14  $result : array<int, string>   ← @return から
    17  $count  : int                   ← @var が mixed を上書き
```

`shout()` の戻り値が `array<int, string>` と分かり、`compute()` が返す `mixed` も
`@var int` で `int` に確定しています。

## まとめ

- 型文字列のパースは `phpstan/phpdoc-parser` に任せ、結果を `Type` に変換する
- `@param`／`@return` はネイティブ宣言より**優先**してリフレクションに取り込む
- `@var` は代入時の推論結果を上書きする
- `ArrayType` で `array<K, V>`／`list<T>`／`T[]` の入口を作った
- クラス名の名前空間解決や配列形状の本格推論は応用編へ

次の Part 8 では、ここまでの型情報を使う本格的なルールを足します。`RuleLevelHelper` と
**引数／戻り値の型不一致**の検出、そして PHPStan の真骨頂である **レベル制**（0〜max で
厳しさを段階的に上げる仕組み）を実装します。
