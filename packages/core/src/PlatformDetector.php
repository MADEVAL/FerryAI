<?php

declare(strict_types=1);

namespace FerryAI\Core;

final class PlatformDetector
{
    public static function os(): string
    {
        return match (PHP_OS_FAMILY) {
            'Windows' => 'windows',
            'Darwin' => 'macos',
            default => 'linux',
        };
    }

    public static function arch(): string
    {
        $machine = \php_uname('m');

        return match ($machine) {
            'x86_64', 'amd64' => 'x86_64',
            'aarch64', 'arm64' => 'aarch64',
            default => $machine,
        };
    }

    public static function libExtension(): string
    {
        return match (self::os()) {
            'windows' => 'dll',
            'macos' => 'dylib',
            default => 'so',
        };
    }

    public static function platformKey(): string
    {
        return self::os() . '-' . self::arch();
    }
}
