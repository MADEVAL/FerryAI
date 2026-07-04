<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\AIFactory;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\OnnxBackend\OnnxBackend;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AIFactory::class)]
final class AIFactoryTest extends TestCase
{
    public function testCreateOnnxBackend(): void
    {
        self::assertInstanceOf(OnnxBackend::class, (new AIFactory())->createBackend(BackendType::Onnx));
    }

    public function testCreateLlamaBackendThrowsInPhase1(): void
    {
        $this->expectException(BackendNotAvailableException::class);

        (new AIFactory())->createBackend(BackendType::Llama);
    }

    public function testCreateCpuNativeBackendThrowsInPhase1(): void
    {
        $this->expectException(BackendNotAvailableException::class);

        (new AIFactory())->createBackend(BackendType::CpuNative);
    }

    public function testCreateTokenizerIsNotImplementedInPhase1(): void
    {
        $this->expectException(\RuntimeException::class);

        (new AIFactory())->createTokenizer('bert-base');
    }

    public function testCreateVectorStoreIsNotImplementedInPhase1(): void
    {
        $this->expectException(\RuntimeException::class);

        (new AIFactory())->createVectorStore('docs', 384);
    }

    public function testCreatePipelineIsNotImplementedInPhase1(): void
    {
        $this->expectException(\RuntimeException::class);

        (new AIFactory())->createPipeline();
    }
}
