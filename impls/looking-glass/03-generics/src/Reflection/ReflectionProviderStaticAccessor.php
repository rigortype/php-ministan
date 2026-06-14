<?php

declare(strict_types=1);

namespace Ministan\Reflection;

/**
 * 現在の {@see ReflectionProvider} への静的アクセス点。
 *
 * 型オブジェクト（{@see \Ministan\Type\ObjectType}）はスコープ推論のあちこちで生成され、
 * provider を引数で持ち回すのが難しい。PHPStan も同じ理由で静的アクセサという「継ぎ目」を
 * 置いている。解析の開始時に {@see set()} され、未設定なら null（= リフレクション無しで
 * 安全側に倒す）を返す。
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
