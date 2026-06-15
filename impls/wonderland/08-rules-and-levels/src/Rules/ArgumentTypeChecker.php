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
     * @param list<Type> $parameterTypes
     * @param array<Arg|\PhpParser\Node\VariadicPlaceholder> $args
     *
     * @return list<array{int, Type, Type}> mismatches as [position (1-based), expected type, actual type]
     */
    public function check(array $parameterTypes, array $args, Scope $scope): array
    {
        $mismatches = [];

        foreach ($args as $position => $arg) {
            if (!$arg instanceof Arg || $arg->unpack || $arg->name !== null) {
                continue; // ...$spread and named arguments break positional correspondence, so skip them
            }
            if (!isset($parameterTypes[$position])) {
                continue; // skip surplus arguments and variadics (non-rejecting)
            }

            $expected = $parameterTypes[$position];
            $actual = $scope->getType($arg->value);

            if (!$this->ruleLevelHelper->isAcceptable($expected, $actual)) {
                $mismatches[] = [$position + 1, $expected, $actual];
            }
        }

        return $mismatches;
    }
}
