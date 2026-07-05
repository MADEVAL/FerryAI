<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\AIFactory;
use FerryAI\Core\Contracts\Pipeline;
use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\Enums\BackendType;
use FerryAI\CpuBackend\CpuNativeBackend;
use FerryAI\LlamaBackend\LlamaBackend;
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

    public function testCreateLlamaBackend(): void
    {
        self::assertInstanceOf(LlamaBackend::class, (new AIFactory())->createBackend(BackendType::Llama));
    }

    public function testCreateCpuNativeBackend(): void
    {
        self::assertInstanceOf(CpuNativeBackend::class, (new AIFactory())->createBackend(BackendType::CpuNative));
    }

    public function testCreateTokenizerByNameRequiresHub(): void
    {
        $this->expectException(\RuntimeException::class);

        (new AIFactory())->createTokenizer('bert-base');
    }

    public function testCreateVectorStore(): void
    {
        $store = (new AIFactory())->createVectorStore('docs', 384);

        self::assertInstanceOf(VectorStore::class, $store);
        self::assertSame(384, $store->dimension());
        self::assertSame('docs', $store->collectionName());
    }

    public function testCreatePipeline(): void
    {
        $pipeline = (new AIFactory())->createPipeline();

        self::assertInstanceOf(Pipeline::class, $pipeline);
    }
}
