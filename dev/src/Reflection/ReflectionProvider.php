<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use ReflectionClass;
use ReflectionFunction;

/**
 * クラス・関数のシグネチャを引く窓口。PHPStan の {@see \PHPStan\Reflection\ReflectionProvider}。
 *
 * 二段構え:
 * 1. 解析対象コードの宣言（AST から事前に収集）
 * 2. 組み込み・vendor のクラス／関数（PHP ネイティブのリフレクションへフォールバック）
 *
 * 「対象コードを実行せず読む」のが理想だが、本チュートリアルでは外部シンボルについては
 * ネイティブのリフレクションに頼る。スタブから組む方式は応用編（The Seasoned）で。
 */
final class ReflectionProvider
{
    /** @var array<string, ClassReflection> */
    private array $classes = [];

    /** @var array<string, FunctionReflection> */
    private array $functions = [];

    private TypeNodeResolver $typeNodeResolver;

    private PhpDocTypeResolver $phpDocTypeResolver;

    public function __construct()
    {
        $this->typeNodeResolver = new TypeNodeResolver();
        $this->phpDocTypeResolver = new PhpDocTypeResolver();
    }

    /**
     * 解析対象 AST からクラス・関数宣言を収集して provider を組む。
     * 名前解決済みなら宣言には namespacedName が付いている前提。
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
                $this->classes[strtolower($name)] = ClassReflection::fromNode($name, $node, $this->typeNodeResolver, $this->phpDocTypeResolver, $this);
            } elseif ($node instanceof Function_) {
                $name = ($node->namespacedName ?? $node->name)->toString();
                $this->functions[strtolower($name)] = FunctionReflection::fromNode($name, $node, $this->typeNodeResolver, $this->phpDocTypeResolver);
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
