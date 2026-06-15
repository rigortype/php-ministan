<?php

declare(strict_types=1);

namespace Ministan\Rules\Methods;

use Ministan\Analyser\Scope;
use Ministan\Reflection\ReflectionProviderStaticAccessor;
use Ministan\Rules\Rule;
use Ministan\Rules\RuleError;
use Ministan\Type\ObjectType;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;

/**
 * Detects a call to a non-existent method on a known class.
 *
 * It strictly honors non-rejecting. It reports only when all of the following hold: the
 * object's type is a determined {@see ObjectType}, that class can be looked up via reflection,
 * the method is definitely absent, and there is no `__call` either. If anything is the least
 * bit uncertain, it stays silent.
 *
 * @implements Rule<MethodCall>
 */
final class CallToUndefinedMethodRule implements Rule
{
    public function processNode(Node $node, Scope $scope): array
    {
        assert($node instanceof MethodCall);

        if (!$node->name instanceof Identifier) {
            return []; // do not follow a dynamic method name like $obj->$m()
        }

        $objectType = $scope->getType($node->var);
        if (!$objectType instanceof ObjectType) {
            return []; // type not determined -> stay silent
        }

        $provider = ReflectionProviderStaticAccessor::getInstanceOrNull();
        if ($provider === null || !$provider->hasClass($objectType->className)) {
            return [];
        }

        $class = $provider->getClass($objectType->className);
        $method = $node->name->toString();

        if ($class->hasMethod($method) || $class->hasMethod('__call')) {
            return [];
        }

        return [new RuleError(
            sprintf('Call to an undefined method %s::%s().', $class->name, $method),
            $node->getStartLine(),
        )];
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }
}
