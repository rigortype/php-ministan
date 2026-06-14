<?php

declare(strict_types=1);

namespace Ministan\Rules;

use PhpParser\Node;

/**
 * ノード種別ごとにルールを索引し、あるノードに対して反応すべきルールを返す。
 *
 * PHPStan の {@see \PHPStan\Rules\Registry} に対応。索引は `getNodeType()` の
 * 戻り値で張るが、引き当て時にはノードの**クラス階層**（親クラス・実装インターフェイス）
 * もたどる。これにより、具象 {@see Node\Expr\FuncCall} を狙うルールも、
 * 抽象 {@see Node\Expr} を狙うルールも、同じ仕組みで共存できる。
 */
final class RuleRegistry
{
    /** @var array<class-string<Node>, list<Rule<Node>>> */
    private array $rules = [];

    /**
     * @param iterable<Rule<Node>> $rules
     */
    public function __construct(iterable $rules)
    {
        foreach ($rules as $rule) {
            $this->rules[$rule->getNodeType()][] = $rule;
        }
    }

    /**
     * @return list<Rule<Node>>
     */
    public function getRulesFor(Node $node): array
    {
        $matched = [];
        foreach ($this->classHierarchy($node) as $class) {
            foreach ($this->rules[$class] ?? [] as $rule) {
                $matched[] = $rule;
            }
        }

        return $matched;
    }

    /**
     * ノード自身のクラスから、親クラス・実装インターフェイスまでを列挙する。
     *
     * @return list<class-string>
     */
    private function classHierarchy(Node $node): array
    {
        $class = $node::class;

        return [
            $class,
            ...array_values(class_parents($class)),
            ...array_values(class_implements($class)),
        ];
    }
}
