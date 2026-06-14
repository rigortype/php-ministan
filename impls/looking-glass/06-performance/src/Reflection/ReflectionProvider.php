<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Analyser\Parsing;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

/**
 * クラス・関数のシグネチャを引く窓口。PHPStan の {@see \PHPStan\Reflection\ReflectionProvider}。
 *
 * 二段構え:
 * 1. 解析対象コードの宣言（AST から事前に収集）
 * 2. 組み込み・vendor のクラス／関数（PHP ネイティブのリフレクションへフォールバック）
 *
 * 「対象コードを実行せず読む」のが理想だが、本チュートリアルでは外部シンボルについては
 * ネイティブのリフレクションに頼る。スタブから組む方式は応用編で。
 */
final class ReflectionProvider
{
    /** @var array<string, ClassReflection> */
    private array $classes = [];

    /** @var array<string, FunctionReflection> */
    private array $functions = [];

    /** @var array<string, FunctionReflection> スタブで補ったシグネチャ（ネイティブより優先） */
    private array $stubFunctions = [];

    private TypeNodeResolver $typeNodeResolver;

    private PhpDocTypeResolver $phpDocTypeResolver;

    public function __construct()
    {
        $this->typeNodeResolver = new TypeNodeResolver();
        $this->phpDocTypeResolver = new PhpDocTypeResolver();
        $this->loadStubs();
    }

    private function loadStubs(): void
    {
        $file = dirname(__DIR__, 2) . '/stubs/core.php';
        if (!is_file($file)) {
            return;
        }

        try {
            $ast = Parsing::parse((string) file_get_contents($file));
        } catch (Throwable) {
            return;
        }

        foreach ($ast as $node) {
            if ($node instanceof Function_) {
                $name = ($node->namespacedName ?? $node->name)->toString();
                $this->stubFunctions[strtolower($name)] = FunctionReflection::fromNode(
                    $name,
                    $node,
                    $this->typeNodeResolver,
                    $this->phpDocTypeResolver,
                );
            }
        }
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
        $key = strtolower($name);

        return isset($this->functions[$key]) || isset($this->stubFunctions[$key]) || function_exists($name);
    }

    public function getFunction(string $name): FunctionReflection
    {
        $name = ltrim($name, '\\');
        $key = strtolower($name);

        // 優先順: 解析対象の宣言 > スタブ > ネイティブ。
        return $this->functions[$key]
            ?? $this->stubFunctions[$key]
            ?? ($this->functions[$key] = FunctionReflection::fromNative(new ReflectionFunction($name), $this->typeNodeResolver));
    }
}
