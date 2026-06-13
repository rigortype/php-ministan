# The Seasoned ministan — S3: ジェネリクス

> ＊コードはライブ実装ツリー [`dev/`](../../../dev) にあります（この章の到達点は `git tag seasoned-03`）。

PHP には言語レベルのジェネリクスがありませんが、PHPDoc の `@template` で表現します。
これを解析できるかどうかが、現代の PHP 静的解析の分水嶺です。

```php
/** @template T @param T $value @return T */
function identity(mixed $value): mixed { return $value; }

$a = identity(42); // mixed ではなく、ちょうど 42 であってほしい
```

## 型変数と型引数

二つの新しい型を導入します。

- [`TemplateType`](../../../dev/src/Type/TemplateType.php) … 「まだ決まっていない型」`T`。
  関係判定は上限境界に委ね、同一性は名前で見る。
- [`GenericObjectType`](../../../dev/src/Type/GenericObjectType.php) … 型引数を伴うオブジェクト
  `Collection<int>`。`ObjectType` を**継承**するので、未定義メソッド検出などの「クラスを
  見る」処理は型引数を無視してそのまま働きます:

```php
final class GenericObjectType extends ObjectType
{
    public function __construct(string $className, public readonly array $typeArguments)
    {
        parent::__construct($className);
    }
}
```

## 置換 —— substitution

ジェネリクスの心臓は **型変数を具体型に置き換える**こと
（[`TemplateTypeMap`](../../../dev/src/Type/TemplateTypeMap.php)）。複合型の中まで再帰します:

```php
public function resolve(Type $type): Type
{
    if ($type instanceof TemplateType)       return $this->map[$type->name] ?? $type;
    if ($type instanceof UnionType)          return TypeCombinator::union(...array_map($this->resolve(...), $type->getTypes()));
    if ($type instanceof ArrayType)          return new ArrayType($this->resolve($type->keyType), $this->resolve($type->itemType));
    if ($type instanceof GenericObjectType)  return new GenericObjectType($type->className, array_map($this->resolve(...), $type->typeArguments));
    return $type;
}
```

## `@template` を読む

[`PhpDocTypeResolver`](../../../dev/src/Reflection/PhpDocTypeResolver.php) に型変数の概念を
足します。`@template T` を集め、その名前の識別子を `TemplateType` に、`Collection<int>` の
ような識別子付きジェネリックを `GenericObjectType` に解決します:

```php
private function fromIdentifier(string $name, array $templateNames): Type
{
    if (in_array($name, $templateNames, true)) {
        return new TemplateType($name, new MixedType()); // 型変数
    }
    // …組み込み型・クラス…
}
```

クラスの型変数はメソッドの `@return T` からも見えるべきなので、クラスの `@template` を
集めてメソッドの docblock 解析に渡します（[`ClassReflection`](../../../dev/src/Reflection/ClassReflection.php)）。

## 呼び出しで置換する

二か所で substitution が起きます（[`Scope`](../../../dev/src/Analyser/Scope.php)）。

**ジェネリック関数** —— 実引数から型変数を推論します:

```php
foreach ($expr->args as $position => $arg) {
    $paramType = $function->parameterTypes[$position] ?? null;
    if ($paramType instanceof TemplateType) {
        $map[$paramType->name] = $this->getType($arg->value); // identity(42) → T=42
    }
}
return (new TemplateTypeMap($map))->resolve($function->returnType);
```

**ジェネリッククラスのメソッド** —— 型引数を型変数に割り当てます:

```php
foreach ($class->templateNames as $i => $templateName) {
    $map[$templateName] = $objectType->typeArguments[$i]; // Box<int> → T=int
}
return (new TemplateTypeMap($map))->resolve($returnType);
```

## 動かす

```console
$ dev/bin/ministan annotate examples/seasoned/generics.php
    14  return : T          ← 関数本体では型変数のまま
    17  $a     : 42          ← identity(42) で T=42 に置換
    18  $b     : 'hello'
    42  $box   : Box<int>    ← @var から
    43  $value : int          ← Box<int>::get(): T を int に置換
```

型変数 `T` が、呼び出しごとに `42`・`'hello'`・`int` へと姿を変えています。これが
ジェネリクスの推論です。

## まとめ

- `TemplateType`（型変数）と `GenericObjectType`（型引数付きオブジェクト）を導入
- `TemplateTypeMap` が複合型の中まで再帰して型変数を置換する
- `@template` をリフレクションに取り込み、クラスの型変数をメソッドにも届ける
- 関数は実引数から、ジェネリッククラスは型引数から、型変数を解決する

> 簡略化: 入れ子の型変数推論（`array<T>` から T を逆算）、境界・変性、プロパティ型の
> 推論は見送り。ここはジェネリクスの「芯」を通すことを優先しました。

次の S4 では、S2 で宿題になった **`match` の腕での絞り込み** を含む、高度な narrowing と
ループの不動点解析に踏み込みます。
