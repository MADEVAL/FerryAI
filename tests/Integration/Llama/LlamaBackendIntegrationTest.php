<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Llama;

use FerryAI\LlamaBackend\LlamaBackend;
use FerryAI\LlamaBackend\Runtime\NativeLlamaRuntime;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real llama.cpp binding.
 *
 * Skipped automatically unless a validated native binding is available
 * (the raw llama.cpp ABI binding is target-specific; see docs/BUILD_LOG.md).
 */
#[Group('integration')]
#[CoversNothing]
final class LlamaBackendIntegrationTest extends TestCase
{
    private LlamaBackend $backend;

    protected function setUp(): void
    {
        if (getenv('FERRY_AI_SKIP_NATIVE') === '1') {
            self::markTestSkipped('Native tests skipped via FERRY_AI_SKIP_NATIVE=1.');
        }

        $this->backend = new LlamaBackend(new NativeLlamaRuntime());

        if (!$this->backend->isAvailable()) {
            self::markTestSkipped('A validated native llama.cpp binding is not available in this environment.');
        }
    }

    public function testReportsAVersion(): void
    {
        self::assertNotSame('', $this->backend->version());
    }

    public function testExposesAvailableDevices(): void
    {
        self::assertNotEmpty($this->backend->availableDevices());
    }
}
