<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\FrameworkConfig;
use FerryAI\Laravel\AIServiceProvider;
use FerryAI\Symfony\DependencyInjection\FerryAIExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: the Laravel and Symfony adapters must derive their configuration from
 * the single shared FrameworkConfig source, so the common defaults stay identical (no drift from
 * the previously byte-for-byte-copied arrays).
 */
#[CoversNothing]
final class FrameworkConfigSharedTest extends TestCase
{
    public function testAdaptersShareIdenticalCommonDefaults(): void
    {
        $laravel = (new AIServiceProvider())->getConfig();

        $extension = new FerryAIExtension();
        $extension->load([]);
        $symfony = $extension->getConfig();

        $shared = FrameworkConfig::defaults();

        foreach (\array_keys($shared) as $key) {
            self::assertSame($shared[$key], $laravel[$key], "Laravel default for '{$key}' must match the shared source.");
            self::assertSame($shared[$key], $symfony[$key], "Symfony default for '{$key}' must match the shared source.");
        }

        // Laravel adds log_channel on top of the shared defaults; the shared set does not include it.
        self::assertArrayHasKey('log_channel', $laravel);
        self::assertArrayNotHasKey('log_channel', $shared);
    }
}
