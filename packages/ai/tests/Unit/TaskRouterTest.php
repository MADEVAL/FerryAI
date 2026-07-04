<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\BackendRegistry;
use FerryAI\Core\Enums\BackendType;
use FerryAI\TaskRouter;
use FerryAI\Tests\Double\StubBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskRouter::class)]
final class TaskRouterTest extends TestCase
{
    private function router(BackendType ...$availableTypes): TaskRouter
    {
        $registry = new BackendRegistry();

        foreach ($availableTypes as $type) {
            $registry->register($type, new StubBackend(true));
        }

        return new TaskRouter($registry);
    }

    public function testRouteForChatPrefersLlama(): void
    {
        self::assertSame(BackendType::Llama, $this->router(BackendType::Llama, BackendType::Onnx)->routeForChat());
    }

    public function testRouteForChatFallsBackToOnnx(): void
    {
        self::assertSame(BackendType::Onnx, $this->router(BackendType::Onnx)->routeForChat());
    }

    public function testRouteForEmbeddingIsOnnx(): void
    {
        self::assertSame(BackendType::Onnx, $this->router()->routeForEmbedding());
    }

    public function testRouteForClassification(): void
    {
        self::assertSame(BackendType::Onnx, $this->router(BackendType::Onnx)->routeForClassification());
        self::assertSame(BackendType::CpuNative, $this->router()->routeForClassification());
    }

    public function testRouteForPrediction(): void
    {
        self::assertSame(BackendType::CpuNative, $this->router()->routeForPrediction());
    }

    public function testRouteForKnownTasks(): void
    {
        $router = $this->router(BackendType::Onnx);

        self::assertSame(BackendType::Onnx, $router->routeFor('embed'));
        self::assertSame(BackendType::Onnx, $router->routeFor('similarity'));
        self::assertSame(BackendType::Onnx, $router->routeFor('classify'));
        self::assertSame(BackendType::CpuNative, $router->routeFor('predict'));
    }

    public function testRouteForUnknownTaskUsesAutoDetect(): void
    {
        self::assertSame(BackendType::Onnx, $this->router(BackendType::Onnx)->routeFor('mystery'));
    }
}
