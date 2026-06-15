# Part 6 — Reflection

> *The code for this chapter lives in the snapshot [`impls/wonderland/06-reflection`](../../../impls/wonderland/06-reflection) — a slice of the live `dev/` tree taken at `git tag part-06`.*

> **Further reading** (optional): TAPL ch. 9, “Simply Typed Lambda-Calculus,” is where a function first gets a type of its own — the arrow `param type → return type`. A method’s signature is exactly that arrow; this chapter looks it up by reflection and puts it to work.

Everything we’ve inferred so far closed over the expression in front of us. Real code, though,
calls into classes.

```php
$greeter = new Greeter();
$message = $greeter->greet('world'); // what does greet() return?
```

To know the type of `$message`, we need to be able to **look up** the signature of
`Greeter::greet()`. The thing that does that lookup is **reflection**. With this chapter the
analyzer enters the stage where it “understands classes.”

## Name resolution first

Reflection can’t fire until we know that the `Greeter` in `use App\Greeter; new Greeter()`
refers to `App\Greeter`. We run the AST through php-parser’s `NameResolver` to resolve names
to their fully qualified form
([`Parsing`](../../../impls/wonderland/06-reflection/src/Analyser/Parsing.php)):

```php
return (new NodeTraverser(new NameResolver()))->traverse($ast);
```

Now every `Name` in a type declaration is an FQN, and every class and function declaration
carries a `namespacedName`. `analyse` and `annotate` share this one extra step.

## `ReflectionProvider` — the window onto signatures

One of PHPStan’s linchpins is
[`ReflectionProvider`](../../../impls/wonderland/06-reflection/src/Reflection/ReflectionProvider.php).
It works in two tiers:

1. **Declarations in the analyzed code** — gathered up front from the AST (`fromNodes()`).
2. **Built-ins and vendor** — falling back to PHP’s native reflection.

```php
public function hasClass(string $name): bool
{
    return isset($this->classes[strtolower($name)])
        || class_exists($name) || interface_exists($name) || enum_exists($name);
}
```

> The ideal is to **read** the target code without ever running it, but in this tutorial we
> lean on native reflection for external symbols. The pure approach — building everything from
> stubs — waits for the advanced volume.

Classes, methods, and functions each get wrapped in
[`ClassReflection`](../../../impls/wonderland/06-reflection/src/Reflection/ClassReflection.php),
[`MethodReflection`](../../../impls/wonderland/06-reflection/src/Reflection/MethodReflection.php), and
[`FunctionReflection`](../../../impls/wonderland/06-reflection/src/Reflection/FunctionReflection.php).
Mapping a type declaration onto a {@see Type} is the job of
[`TypeNodeResolver`](../../../impls/wonderland/06-reflection/src/Reflection/TypeNodeResolver.php),
which handles both php-parser’s type nodes and PHP’s native `ReflectionType` (turning `?Foo`
into `Foo|null`, for instance — this is where Part 5’s `UnionType` starts to earn its keep).

> Reading note: TAPL’s simply typed lambda-calculus gives a function the type
> `T₁ → T₂` — a pair of “the argument type” and “the result type.”
> `MethodReflection` and `FunctionReflection` bundle the same thing — a list of parameter
> types plus a return type — except that each one is **looked up**, method by method, from the
> source or from native reflection rather than written down in a derivation.

## How a type object reaches the provider — the static-accessor seam

`ObjectType` gets constructed all over the inference machinery, so we can’t thread the
provider through as an argument everywhere. Just as PHPStan does, we lay down a **static
accessor** as a seam
([`ReflectionProviderStaticAccessor`](../../../impls/wonderland/06-reflection/src/Reflection/ReflectionProviderStaticAccessor.php)).
We `set()` it at the start of analysis; if it was never set, it returns null — that is,
no reflection, which is the safe side.

With this in hand we can promote the naive `ObjectType` of Part 5 to one that
**understands inheritance**:

```php
private function isSuperTypeOfClass(string $other): TrinaryLogic
{
    if (strcasecmp($this->className, $other) === 0) return TrinaryLogic::Yes;

    $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
    if ($provider !== null && $provider->hasClass($other)) {
        return $provider->getClass($other)->isSubclassOf($this->className)
            ? TrinaryLogic::Yes : TrinaryLogic::No;
    }
    return TrinaryLogic::Maybe; // no hierarchy in hand → neither narrow nor widen
}
```

For `class B extends A`, `A ⊇ B` is Yes and `B ⊇ A` is No. The `instanceof` narrowing we
shelved in Part 5 now genuinely bites.

## Inferring a call’s return value

We add three reflection-using expressions to `Scope::getType()`:

```php
$expr instanceof Expr\New_        => new ObjectType($expr->class->toString()),
$expr instanceof Expr\MethodCall  => $this->methodCallType($expr),  // return of $obj->m()
$expr instanceof Expr\FuncCall    => $this->funcCallType($expr),    // return of f()
```

`methodCallType()` returns a return type only when the receiver’s type is settled as an
`ObjectType`, that class can be looked up, and the method exists; at the slightest uncertainty
it collapses to `mixed`.

## Calling a method that isn’t there

If we know the return value, we also know about a **call to a method that doesn’t exist**
([`CallToUndefinedMethodRule`](../../../impls/wonderland/06-reflection/src/Rules/Methods/CallToUndefinedMethodRule.php)).
Holding the line on non-rejecting, we report only when **all** of these line up: the type is
settled, the class is known, the method is definitely absent, and there’s no `__call` either.

```console
$ dev/bin/ministan annotate examples/reflection.php
examples/reflection.php
     9  return   : string
    13  $greeter : Greeter
    14  $message : string   ← return of greet()
    15  $length  : int      ← return of strlen()

$ dev/bin/ministan analyse examples/reflection.php
 examples/reflection.php:17
   Call to an undefined method Greeter::shout().
```

We can infer the return value of a method and of the built-in `strlen()` alike.

> **What about Laravel’s magic?** The return of `User::find()`, dynamic properties like
> `$user->name`, facades, `Collection` macros — these run on **magic methods**
> (`__call` / `__get` / `__callStatic`) and other runtime machinery, and their signatures
> can’t be traced *statically*. On unknowns like these, ministan (and plain PHPStan) collapses
> to `mixed` and **stays quiet** — which is exactly why undefined-method detection backs off
> the moment a `__call` is present. To analyze something like Eloquent precisely, you need
> **stubs or extensions** that translate the magic into types (for PHPStan that’s an extension
> such as larastan; for ministan, we take up stubs in the advanced volume, S5). “All of my
> Laravel code gets fully analyzed as-is” is not the deal — but everywhere you wrote a type
> down, it works.

## Summary

- Resolve names to FQNs with `NameResolver` before reaching for reflection.
- `ReflectionProvider` looks up signatures in two tiers: declarations in the target code, plus
  native reflection.
- Type objects reach the provider through a **static-accessor** seam, which makes `ObjectType`
  inheritance-aware.
- We infer the return value of method and function calls, and detect **calls to undefined
  methods**.
- When anything is unknown, always collapse to `mixed` / `Maybe` (non-rejecting).

In the next chapter, Part 7, we read **PHPDoc**. We bring in `phpstan/phpdoc-parser`, take
`@param` / `@return` / `@var` in preference to the native declarations, and open the door to
array shapes like `array<int, string>` and `list<T>`.
