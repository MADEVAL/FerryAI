<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit;

use FerryAI\Core\PlatformDetector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlatformDetector::class)]
final class PlatformDetectorTest extends TestCase
{
    public function testOsReturnsString(): void
    {
        $os = PlatformDetector::os();

        self::assertIsString($os);
        self::assertNotEmpty($os);
        self::assertContains($os, ['linux', 'macos', 'windows']);
    }

    public function testArchReturnsString(): void
    {
        $arch = PlatformDetector::arch();

        self::assertIsString($arch);
        self::assertNotEmpty($arch);
    }

    public function testLibExtensionReturnsPlatformSpecific(): void
    {
        $ext = PlatformDetector::libExtension();
        $os = PlatformDetector::os();

        $expected = match ($os) {
            'windows' => 'dll',
            'macos' => 'dylib',
            default => 'so',
        };

        self::assertSame($expected, $ext);
    }

    public function testPlatformKeyIsOsArch(): void
    {
        $key = PlatformDetector::platformKey();

        self::assertStringContainsString(PlatformDetector::os(), $key);
        self::assertStringContainsString('-', $key);
    }
}
