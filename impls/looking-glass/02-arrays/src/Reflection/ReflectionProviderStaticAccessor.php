<?php

declare(strict_types=1);

namespace Ministan\Reflection;

/**
 * A static access point to the current {@see ReflectionProvider}.
 *
 * Type objects (e.g. {@see \Ministan\Type\ObjectType}) are created all over scope inference,
 * which makes threading the provider through arguments awkward. For the same reason PHPStan
 * places a static accessor as a "seam." It is populated via {@see set()} at the start of analysis,
 * and returns null when unset (= falling back to the safe side, without reflection).
 */
final class ReflectionProviderStaticAccessor
{
    private static ?ReflectionProvider $instance = null;

    public static function set(ReflectionProvider $provider): void
    {
        self::$instance = $provider;
    }

    public static function getInstanceOrNull(): ?ReflectionProvider
    {
        return self::$instance;
    }
}
