<?php

declare(strict_types=1);

namespace Ministan\Rules;

use Ministan\TrinaryLogic;
use Ministan\Type\Type;

/**
 * Decides, depending on the level, "whether the actual type may be put into the expected type".
 *
 * The core that corresponds to PHPStan's {@see \PHPStan\Rules\RuleLevelHelper}. The key is the
 * three-valued logic:
 * - Yes (definitely conforms) -> always OK
 * - No (definitely does not conform) -> always an error
 * - Maybe (uncertain, e.g. with mixed) -> an error **only at high levels**
 *
 * This is what "the higher the level, the stricter" really is. At low levels it waves mixed
 * through; at high levels it also flags mixed creeping in. The non-rejecting philosophy is
 * placed onto the level axis.
 */
final readonly class RuleLevelHelper
{
    /** At this level or above, treat "Maybe" as not conforming. */
    private const int STRICT_LEVEL = 7;

    public function __construct(
        private int $level,
    ) {
    }

    public function isAcceptable(Type $accepting, Type $accepted): bool
    {
        return match ($accepting->accepts($accepted)) {
            TrinaryLogic::Yes => true,
            TrinaryLogic::No => false,
            TrinaryLogic::Maybe => $this->level < self::STRICT_LEVEL,
        };
    }
}
