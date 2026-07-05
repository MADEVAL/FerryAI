<?php

declare(strict_types=1);

namespace FerryAI\Symfony\Tests\Unit;

use FerryAI\AI;
use FerryAI\Symfony\AIBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AIBundle::class)]
final class AIBundleTest extends TestCase
{
    protected function setUp(): void
    {
        AI::reset();
    }

    protected function tearDown(): void
    {
        AI::reset();
    }

    public function testBootCallsAIConfig(): void
    {
        $bundle = new AIBundle();

        $bundle->boot();

        self::assertSame(\FerryAI\Core\Enums\BackendType::Onnx, AI::activeBackend());
    }

    public function testGetDefaultConfigReturnsStructure(): void
    {
        $bundle = new AIBundle();

        $config = $bundle->getDefaultConfig();

        self::assertIsArray($config);
        self::assertArrayHasKey('backend', $config);
        self::assertArrayHasKey('device', $config);
        self::assertArrayHasKey('model_cache', $config);
    }
}
