<?php

declare(strict_types=1);

namespace FerryAI\Laravel\Tests\Unit;

use FerryAI\Laravel\Facades\AI as AIFacade;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AIFacade::class)]
final class AIFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        \FerryAI\AI::reset();
    }

    protected function tearDown(): void
    {
        \FerryAI\AI::reset();
    }

    public function testCallStaticProxiesToAI(): void
    {
        \FerryAI\AI::config(['backend' => 'onnx']);

        $backend = AIFacade::activeBackend();

        self::assertSame(\FerryAI\Core\Enums\BackendType::Onnx, $backend);
    }

    public function testCallStaticWithUnknownMethodForwards(): void
    {
        \FerryAI\AI::config(['backend' => 'onnx']);

        $this->expectException(\Error::class);

        AIFacade::nonExistentMethod('arg');
    }
}
