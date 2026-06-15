<?php

declare(strict_types=1);

namespace Ministan\Reflection;

use Ministan\Type\MixedType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use ReflectionClass;

/**
 * The signature of a class or interface. Provides inheritance resolution and method lookup.
 *
 * It unifies two sources into one shape: AST-derived declarations (the code under analysis)
 * and native-derived classes (built-in and vendor classes). Supertypes are held as class
 * names and walked recursively through {@see ReflectionProvider} when needed.
 */
final class ClassReflection
{
    /**
     * @param list<string> $parentNames supertype class names from extends / implements (FQN)
     * @param array<string, MethodReflection> $methods lowercased name => method (directly declared ones)
     * @param list<string> $templateNames the type variables this class declares (@template)
     */
    public function __construct(
        public readonly string $name,
        private readonly array $parentNames,
        private readonly array $methods,
        private readonly ReflectionProvider $provider,
        private readonly ?ReflectionClass $native = null,
        public readonly array $templateNames = [],
    ) {
    }

    public static function fromNode(string $name, ClassLike $node, TypeNodeResolver $resolver, PhpDocTypeResolver $phpDoc, ReflectionProvider $provider): self
    {
        $parents = [];
        if ($node instanceof Class_) {
            if ($node->extends !== null) {
                $parents[] = $node->extends->toString();
            }
            foreach ($node->implements as $interface) {
                $parents[] = $interface->toString();
            }
        } elseif ($node instanceof Interface_) {
            foreach ($node->extends as $interface) {
                $parents[] = $interface->toString();
            }
        } elseif ($node instanceof Enum_) {
            foreach ($node->implements as $interface) {
                $parents[] = $interface->toString();
            }
        }

        $templateNames = $phpDoc->parse($node->getDocComment()?->getText())->templateNames;

        $methods = [];
        foreach ($node->getMethods() as $method) {
            $methods[strtolower($method->name->toString())] = MethodReflection::fromNode($method, $resolver, $phpDoc, $templateNames);
        }

        return new self($name, $parents, $methods, $provider, null, $templateNames);
    }

    /**
     * @param ReflectionClass<object> $native
     */
    public static function fromNative(ReflectionClass $native, ReflectionProvider $provider): self
    {
        $parents = [];
        $parent = $native->getParentClass();
        if ($parent !== false) {
            $parents[] = $parent->getName();
        }
        foreach ($native->getInterfaceNames() as $interface) {
            $parents[] = $interface;
        }

        return new self($native->getName(), $parents, [], $provider, $native);
    }

    public function isSubclassOf(string $target): bool
    {
        $target = ltrim($target, '\\');
        if (strcasecmp($this->name, $target) === 0) {
            return true;
        }

        foreach ($this->parentNames as $parent) {
            if (strcasecmp($parent, $target) === 0) {
                return true;
            }
            if ($this->provider->hasClass($parent) && $this->provider->getClass($parent)->isSubclassOf($target)) {
                return true;
            }
        }

        return false;
    }

    public function hasMethod(string $name): bool
    {
        if (isset($this->methods[strtolower($name)])) {
            return true;
        }

        if ($this->native !== null) {
            return $this->native->hasMethod($name);
        }

        foreach ($this->parentNames as $parent) {
            if ($this->provider->hasClass($parent) && $this->provider->getClass($parent)->hasMethod($name)) {
                return true;
            }
        }

        return false;
    }

    public function getMethod(string $name): MethodReflection
    {
        $key = strtolower($name);
        if (isset($this->methods[$key])) {
            return $this->methods[$key];
        }

        if ($this->native !== null && $this->native->hasMethod($name)) {
            return MethodReflection::fromNative($this->native->getMethod($name), new TypeNodeResolver());
        }

        foreach ($this->parentNames as $parent) {
            if ($this->provider->hasClass($parent) && $this->provider->getClass($parent)->hasMethod($name)) {
                return $this->provider->getClass($parent)->getMethod($name);
            }
        }

        return new MethodReflection($name, new MixedType(), []);
    }
}
