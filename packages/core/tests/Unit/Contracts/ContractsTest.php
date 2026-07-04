<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Contracts;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\DataFrame;
use FerryAI\Core\Contracts\Embedder;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Contracts\ModelHub;
use FerryAI\Core\Contracts\Pipeline;
use FerryAI\Core\Contracts\Stage;
use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Contracts\VectorStore;
use PHPUnit\Framework\TestCase;

final class ContractsTest extends TestCase
{
    /**
     * @param class-string $interface
     * @param string[]     $methods
     */
    private function assertContract(string $interface, array $methods): void
    {
        self::assertTrue(interface_exists($interface), "Interface {$interface} must exist");

        foreach ($methods as $method) {
            self::assertTrue(
                method_exists($interface, $method),
                "{$interface} must declare {$method}()",
            );
        }
    }

    public function testBackendContract(): void
    {
        $this->assertContract(Backend::class, ['availableDevices', 'load', 'version', 'isAvailable']);
    }

    public function testModelContract(): void
    {
        $this->assertContract(Model::class, ['run', 'inputs', 'outputs', 'metadata', 'device', 'unload']);
    }

    public function testTensorContract(): void
    {
        $this->assertContract(Tensor::class, [
            'shape', 'dtype', 'to', 'device', 'toArray', 'data',
            'add', 'sub', 'mul', 'matmul', 'transpose', 'reshape', 'slice',
        ]);

        $parents = class_implements(Tensor::class);

        self::assertContains(\ArrayAccess::class, $parents);
        self::assertContains(\Countable::class, $parents);
        self::assertContains(\JsonSerializable::class, $parents);
    }

    public function testTokenizerContract(): void
    {
        $this->assertContract(Tokenizer::class, [
            'encode', 'decode', 'encodeBatch', 'vocabSize', 'type',
            'specialTokenId', 'specialTokens', 'countTokens', 'chunk',
        ]);
    }

    public function testEmbedderContract(): void
    {
        $this->assertContract(Embedder::class, [
            'embed', 'embedBatch', 'dimension', 'normalize', 'cosineSimilarity', 'modelName',
        ]);
    }

    public function testVectorStoreContract(): void
    {
        $this->assertContract(VectorStore::class, [
            'add', 'addBatch', 'search', 'delete', 'deleteByFilter', 'update',
            'count', 'dimension', 'collectionName', 'iterator', 'export', 'clear',
        ]);
    }

    public function testPipelineContract(): void
    {
        $this->assertContract(Pipeline::class, ['pipe', 'run', 'stages', '__invoke']);
    }

    public function testStageContract(): void
    {
        $this->assertContract(Stage::class, ['process', 'name']);
    }

    public function testModelHubContract(): void
    {
        $this->assertContract(ModelHub::class, [
            'download', 'cached', 'verify', 'introspect', 'downloadWithProgress',
            'remove', 'prune', 'cacheSize', 'warmup',
        ]);
    }

    public function testDataFrameContract(): void
    {
        self::assertTrue(interface_exists(DataFrame::class));

        $parents = class_implements(DataFrame::class);

        self::assertContains(\Iterator::class, $parents);
        self::assertContains(\Countable::class, $parents);
    }
}
