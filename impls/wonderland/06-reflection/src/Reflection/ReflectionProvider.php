<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use ReflectionClass;
use ReflectionFunction;

/**
 * The entry point for looking up class and function signatures. PHPStan's {@see \PHPStan\Reflection\ReflectionProvider}.
 *
 * Two tiers:
 * 1. Declarations from the code under analysis (collected up front from the AST)
 * 2. Built-in and vendor classes/functions (falling back to PHP's native reflection)
 *
 * Ideally we would "read the target code without running it," but in this tutorial we rely on
 * native reflection for external symbols. Building from stubs is covered in the advanced volume.
 */
final class ReflectionProvider
{
    /** @var array<string, ClassReflection> */
    private array $classes = [];

    /** @var array<string, FunctionReflection> */
    private array $functions = [];

    private TypeNodeResolver $typeNodeResolver;

    public function __construct()
    {
        $this->typeNodeResolver = new TypeNodeResolver();
    }

    /**
     * Builds the provider by collecting class and function declarations from the target AST.
     * Assumes that, once name resolution has run, declarations carry a namespacedName.
     *
     * @param Node[] $stmts
     */
    public static function fromNodes(array $stmts): self
    {
        $provider = new self();
        $provider->collect($stmts);

        return $provider;
    }

    /**
     * @param Node[] $nodes
     */
    private function collect(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Node\Stmt\Namespace_) {
                $this->collect($node->stmts);
            } elseif ($node instanceof ClassLike && $node->name !== null) {
                $name = ($node->namespacedName ?? $node->name)->toString();
                $this->classes[strtolower($name)] = ClassReflection::fromNode($name, $node, $this->typeNodeResolver, $this);
            } elseif ($node instanceof Function_) {
                $name = ($node->namespacedName ?? $node->name)->toString();
                $this->functions[strtolower($name)] = FunctionReflection::fromNode($name, $node, $this->typeNodeResolver);
            }
        }
    }

    public function hasClass(string $name): bool
    {
        $name = ltrim($name, '\\');

        return isset($this->classes[strtolower($name)])
            || class_exists($name)
            || interface_exists($name)
            || enum_exists($name);
    }

    public function getClass(string $name): ClassReflection
    {
        $name = ltrim($name, '\\');
        $key = strtolower($name);

        if (isset($this->classes[$key])) {
            return $this->classes[$key];
        }

        /** @var ReflectionClass<object> $native */
        $native = new ReflectionClass($name);

        return $this->classes[$key] = ClassReflection::fromNative($native, $this);
    }

    public function hasFunction(string $name): bool
    {
        $name = ltrim($name, '\\');

        return isset($this->functions[strtolower($name)]) || function_exists($name);
    }

    public function getFunction(string $name): FunctionReflection
    {
        $name = ltrim($name, '\\');
        $key = strtolower($name);

        if (isset($this->functions[$key])) {
            return $this->functions[$key];
        }

        return $this->functions[$key] = FunctionReflection::fromNative(
            new ReflectionFunction($name),
            $this->typeNodeResolver,
        );
    }
}
