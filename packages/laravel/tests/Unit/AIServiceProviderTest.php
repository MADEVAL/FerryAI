<?php

declare(strict_types=1);

namespace FerryAI\Laravel\Tests\Unit;

use FerryAI\AI;
use FerryAI\Laravel\AIServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AIServiceProvider::class)]
final class AIServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        AI::reset();
    }

    protected function tearDown(): void
    {
        AI::reset();
    }

    public function testRegisterCallsAIConfig(): void
    {
        $provider = new AIServiceProvider();

        $provider->register();

        self::assertSame(\FerryAI\Core\Enums\BackendType::Onnx, AI::activeBackend());
    }

    public function testGetConfigReturnsExpectedStructure(): void
    {
        $provider = new AIServiceProvider();

        $config = $provider->getConfig();

        self::assertIsArray($config);
        self::assertArrayHasKey('backend', $config);
        self::assertArrayHasKey('device', $config);
        self::assertArrayHasKey('model_cache', $config);
        self::assertArrayHasKey('max_tokens', $config);
        self::assertArrayHasKey('temperature', $config);
        self::assertArrayHasKey('backends', $config);
    }

    public function testGetConfigReadsEnv(): void
    {
        \putenv('FERRY_AI_BACKEND=llama');
        \putenv('FERRY_AI_MAX_TOKENS=4096');

        try {
            $provider = new AIServiceProvider();
            $config = $provider->getConfig();

            self::assertSame('llama', $config['backend']);
            self::assertSame(4096, $config['max_tokens']);
        } finally {
            \putenv('FERRY_AI_BACKEND');
            \putenv('FERRY_AI_MAX_TOKENS');
        }
    }

    public function testBootDoesNotError(): void
    {
        $provider = new AIServiceProvider();

        $provider->boot();

        self::assertTrue(true);
    }

    public function testGetConfigPreservesZeroTemperature(): void
    {
        \putenv('FERRY_AI_TEMPERATURE=0');

        try {
            $config = (new AIServiceProvider())->getConfig();

            self::assertSame(0.0, $config['temperature']);
        } finally {
            \putenv('FERRY_AI_TEMPERATURE');
        }
    }

    public function testGetConfigAllowsDisablingSignatureVerification(): void
    {
        \putenv('FERRY_AI_VERIFY_SIGNATURES=0');

        try {
            $config = (new AIServiceProvider())->getConfig();

            self::assertFalse($config['verify_signatures']);
        } finally {
            \putenv('FERRY_AI_VERIFY_SIGNATURES');
        }
    }

    public function testGetConfigSetsLogLevel(): void
    {
        \putenv('FERRY_AI_LOG_LEVEL=debug');

        try {
            $config = (new AIServiceProvider())->getConfig();

            self::assertSame('debug', $config['log_level']);
        } finally {
            \putenv('FERRY_AI_LOG_LEVEL');
        }
    }
}
