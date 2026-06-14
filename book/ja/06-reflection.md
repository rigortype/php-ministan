# Part 6 — リフレクション

> ＊この章のコードはスナップショット [`impls/06-reflection`](../../impls/06-reflection) にあります（この章の到達点は `git tag part-06`）。

> 参考書（任意）：『しくみ』3 章「関数型」／TAPL 9 章「単純型付きラムダ計算」。メソッドのシグネチャ＝「引数の型 → 戻りの型」を、リフレクションで引いて使います。

ここまでの推論は、目の前の式だけで完結していました。でも実際のコードはクラスを呼びます。

```php
$greeter = new Greeter();
$message = $greeter->greet('world'); // greet() は何を返す？
```

`$message` の型を知るには、`Greeter::greet()` のシグネチャを**引ける**必要があります。
それを担うのが **リフレクション** です。本章で解析器は「クラスを理解する」段階に入ります。

## まず名前解決

`use App\Greeter; new Greeter()` の `Greeter` が `App\Greeter` を指すと知らねば、
リフレクションは引けません。php-parser の `NameResolver` を通して、名前を完全修飾へ
解決します（[`Parsing`](../../impls/06-reflection/src/Analyser/Parsing.php)）:

```php
return (new NodeTraverser(new NameResolver()))->traverse($ast);
```

これで型宣言の `Name` は FQN になり、クラス・関数宣言には `namespacedName` が付きます。
`analyse` と `annotate` は、このひと手間を共有します。

## `ReflectionProvider` —— シグネチャを引く窓口

PHPStan の要のひとつ [`ReflectionProvider`](../../impls/06-reflection/src/Reflection/ReflectionProvider.php)。
二段構えです:

1. **解析対象コードの宣言** —— AST から事前に収集（`fromNodes()`）
2. **組み込み・vendor** —— PHP ネイティブのリフレクションにフォールバック

```php
public function hasClass(string $name): bool
{
    return isset($this->classes[strtolower($name)])
        || class_exists($name) || interface_exists($name) || enum_exists($name);
}
```

> 「対象コードを実行せず読む」のが理想ですが、本チュートリアルでは外部シンボルは
> ネイティブのリフレクションに頼ります。スタブから組む純粋な方式は応用編へ。

クラス／メソッド／関数はそれぞれ
[`ClassReflection`](../../impls/06-reflection/src/Reflection/ClassReflection.php)・
[`MethodReflection`](../../impls/06-reflection/src/Reflection/MethodReflection.php)・
[`FunctionReflection`](../../impls/06-reflection/src/Reflection/FunctionReflection.php) に包みます。型宣言を
{@see Type} に写すのは [`TypeNodeResolver`](../../impls/06-reflection/src/Reflection/TypeNodeResolver.php) の
仕事で、php-parser の型ノードと PHP ネイティブの `ReflectionType` の両方を扱います
（`?Foo` → `Foo|null` など、Part 5 の `UnionType` がここで効きます）。

> 参考書メモ：『しくみ』3 章（TAPL 9 章）は関数の型を `{ params, retType }` というデータで持ちました。
> `MethodReflection`／`FunctionReflection` も同じく「引数の型ならび ＋ 戻りの型」を束ねたもの —— ただし
> メソッドごとに、ソースやネイティブのリフレクションから引いてくる点が違います。

## 型オブジェクトはどう provider を引くか —— 静的アクセサという継ぎ目

`ObjectType` は推論のあちこちで生成されるので、provider を引数で持ち回せません。
PHPStan と同じく、**静的アクセサ**という継ぎ目を置きます
（[`ReflectionProviderStaticAccessor`](../../impls/06-reflection/src/Reflection/ReflectionProviderStaticAccessor.php)）。
解析開始時に `set()` し、未設定なら null（＝リフレクション無しで安全側）を返します。

これで Part 5 で素朴だった `ObjectType` を**継承対応**に強化できます:

```php
private function isSuperTypeOfClass(string $other): TrinaryLogic
{
    if (strcasecmp($this->className, $other) === 0) return TrinaryLogic::Yes;

    $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
    if ($provider !== null && $provider->hasClass($other)) {
        return $provider->getClass($other)->isSubclassOf($this->className)
            ? TrinaryLogic::Yes : TrinaryLogic::No;
    }
    return TrinaryLogic::Maybe; // 階層が分からなければ狭めも広げもしない
}
```

`class B extends A` のとき `A ⊇ B` は Yes、`B ⊇ A` は No。Part 5 で見送った `instanceof`
の絞り込みが、これで本当に効くようになりました。

## 呼び出しの戻り値を推論する

`Scope::getType()` に、リフレクションを使う 3 つの式を足します:

```php
$expr instanceof Expr\New_        => new ObjectType($expr->class->toString()),
$expr instanceof Expr\MethodCall  => $this->methodCallType($expr),  // $obj->m() の戻り値
$expr instanceof Expr\FuncCall    => $this->funcCallType($expr),    // f() の戻り値
```

`methodCallType()` は、レシーバーの型が確定した `ObjectType` で、そのクラスが引けて、
メソッドがあるときだけ戻り値型を返し、少しでも不明なら `mixed` に縮退します。

## 未定義メソッドを叩く

戻り値が分かるなら、**存在しないメソッド呼び出し**も分かります
（[`CallToUndefinedMethodRule`](../../impls/06-reflection/src/Rules/Methods/CallToUndefinedMethodRule.php)）。
non-rejecting を厳守し、「型が確定・クラスが既知・メソッドが確実に無い・`__call` も無い」の
全部が揃ったときだけ報告します。

```console
$ dev/bin/ministan annotate examples/reflection.php
examples/reflection.php
     9  return   : string
    13  $greeter : Greeter
    14  $message : string   ← greet() の戻り値
    15  $length  : int      ← strlen() の戻り値

$ dev/bin/ministan analyse examples/reflection.php
 examples/reflection.php:17
   Call to an undefined method Greeter::shout().
```

メソッドの戻り値も、組み込み関数 `strlen()` の戻り値も推論できています。

> **Laravel の magic はどうなる？** `User::find()` の戻り値や `$user->name` のような動的
> プロパティ、ファサード、`Collection` のマクロ —— これらは `__call`/`__get`/`__callStatic`
> という**魔法のメソッド**やランタイムの仕掛けで動いていて、シグネチャを*静的には*辿れません。
> ministan（や素の PHPStan）は、こうした不明点では `mixed` に縮退して**黙ります**（だから
> 未定義メソッド検出も `__call` があれば見送ります）。Eloquent などを精密に解析するには、
> magic を型に翻訳する**スタブや拡張**が要ります（PHPStan なら larastan のような拡張、
> ministan ではスタブを応用編 S5 で扱います）。「自分の Laravel コードがそのまま全部
> 解析される」わけではない —— が、型を書いた所はちゃんと効きます。

## まとめ

- `NameResolver` で名前を FQN に解決してからリフレクションを引く
- `ReflectionProvider` が「対象コードの宣言＋ネイティブ」の二段構えでシグネチャを引く
- 型オブジェクトは**静的アクセサ**という継ぎ目で provider に届き、`ObjectType` が
  継承対応になる
- メソッド／関数呼び出しの戻り値を推論し、**未定義メソッド呼び出し**を検出する
- 不明なら必ず `mixed`／`Maybe` に縮退（non-rejecting）

次の Part 7 では **PHPDoc** を読みます。`phpstan/phpdoc-parser` を導入し、`@param`／
`@return`／`@var` をネイティブ型より優先して取り込み、`array<int, string>`／`list<T>`
といった配列形状の入口を作ります。
