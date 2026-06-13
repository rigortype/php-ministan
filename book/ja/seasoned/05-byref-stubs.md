# The Seasoned ministan — S5: 参照渡しとスタブ

> ＊コードはライブ実装ツリー [`dev/`](../../../dev) にあります（この章の到達点は `git tag seasoned-05`）。

Part 8 で自分自身を解析したとき、こう叱られました:

```php
preg_match('/\d+/', $input, $matches);
//                           ^^^^^^^^ Undefined variable: $matches
```

`$matches` は **参照渡しの出力引数**で、`preg_match` が中身を書き込みます。でも解析器には
「読み取り」に見えて、未定義だと誤検出していました。本章でこれを解消し、ついでに
**スタブ**でシグネチャを補う仕組みを入れます。

## 参照渡しを知る

まずリフレクションに「どのパラメータが参照渡しか」を持たせます。ネイティブ関数は
`ReflectionParameter::isPassedByReference()`、解析対象コードは `Param::$byRef` から
分かります（[`FunctionReflection`](../../../dev/src/Reflection/FunctionReflection.php)）:

```php
array_map(static fn ($param): bool => $param->isPassedByReference(), $function->getParameters());
```

## 出力引数は「定義」

これまで呼び出しの引数はすべて「読み取り」として処理していました。参照渡しの位置に
変数が来たら、それは **書き込み（定義）** です。`NodeScopeResolver` で呼び出しを専用処理し、
その引数だけ変数を定義します（[`processCallArgs`](../../../dev/src/Analyser/NodeScopeResolver.php)）:

```php
if (($byRef[$position] ?? false) && $arg->value instanceof Expr\Variable) {
    $scope = $scope->assignVariable($arg->value->name, new MixedType()); // 出力引数を定義
} else {
    $scope = $this->processNode($arg->value, $scope);                    // 通常の読み取り
}
```

```console
$ dev/bin/ministan analyse examples/seasoned/byref-stubs.php
[OK] No errors   # $matches はもう未定義扱いされない
```

Part 8 の宿題を回収しました。

## スタブ —— シグネチャを外から補う

ネイティブのリフレクションでは表現できない型があります。`explode()` の戻り値はネイティブ
では `array`（＝ ministan では `mixed`）ですが、本当は `list<string>` です。PHPStan は
**スタブ**（functionMap / `.stub`）でこれを補います。ministan も同じ手を使います。

[`dev/stubs/core.php`](../../../dev/stubs/core.php) は**実行されません**。シグネチャを読むためだけに
**パース**されます:

```php
/** @return list<string> */
function explode(string $separator, string $string, int $limit = PHP_INT_MAX): array {}
```

[`ReflectionProvider`](../../../dev/src/Reflection/ReflectionProvider.php) は起動時にこれを読み、
ネイティブより優先します:

```php
// 優先順: 解析対象の宣言 > スタブ > ネイティブ
return $this->functions[$key] ?? $this->stubFunctions[$key] ?? /* ネイティブ */;
```

```console
$ dev/bin/ministan annotate examples/seasoned/byref-stubs.php
    14  $parts : array<int, string>   ← スタブの list<string> から
    15  $first : string                ← $parts[0]
```

## まとめ

- 参照渡しのパラメータをリフレクションに持たせ、その位置の引数変数を**定義**として扱う
- これで `preg_match(..., $m)` の `$m` が未定義扱いされない（Part 8 の宿題を回収）
- **スタブ**でネイティブに足りないシグネチャを補い、ネイティブより優先する
- スタブは実行せず**パース**するだけ——PHPStan の functionMap と同じ発想

> 簡略化: 名前付き引数の型照合（位置への対応付け）、参照渡しの出力型の精密化
> （`@param string[] $matches` を $matches の型にする）は見送り。

最終章 S6 では、これを大規模コードベースで現実的に使うための **結果キャッシュ** を
実装します。
