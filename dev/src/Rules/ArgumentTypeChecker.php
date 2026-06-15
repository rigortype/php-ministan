<?php

declare(strict_types=1);

namespace Ministan\Rules;

use Ministan\Analyser\Scope;
use Ministan\Type\Type;
use PhpParser\Node\Arg;

/**
 * Shared logic that matches a call's actual arguments against the declared parameter types.
 * The function-call and method-call rules share this.
 */
final readonly class ArgumentTypeChecker
{
    public function __construct(
        private RuleLevelHelper $ruleLevelHelper,
    ) {
    }

    /**
     * @param list<Type>   $parameterTypes
     * @param list<string> $parameterNames names used to map named arguments to positions
     * @param array<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     *
     * @return list<array{int, Type, Type}> mismatches as [position (1-based), expected type, actual type]
     */
    public function check(array $parameterTypes, array $parameterNames, array $args, Scope $scope): array
    {
        $mismatches = [];

        foreach ($args as $position => $arg) {
            if (!$arg instanceof Arg || $arg->unpack) {
                continue; // ...$spread breaks positional correspondence, so skip it
            }

            // For a named argument, resolve its position in the declaration from its name.
            $index = $arg->name !== null
                ? array_search($arg->name->toString(), $parameterNames, true)
                : $position;
            if ($index === false || !isset($parameterTypes[$index])) {
                continue;
            }

            $expected = $parameterTypes[$index];
            $actual = $scope->getType($arg->value);

            if (!$this->ruleLevelHelper->isAcceptable($expected, $actual)) {
                $mismatches[] = [$index + 1, $expected, $actual];
            }
        }

        return $mismatches;
    }
}
