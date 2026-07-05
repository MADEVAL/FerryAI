<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Onnx;

use FerryAI\AI;
use FerryAI\Core\ValueObjects\EmbeddingResult;
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\OnnxBackend\Runtime\NativeOnnxRuntime;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end embeddings through the AI facade with a real ONNX model, driven entirely
 * by config (`backends.embedding.model_path`).
 *
 * Point FERRY_AI_MODEL_DIR at a directory containing `model.onnx` + `tokenizer.json`
 * (e.g. sentence-transformers/all-MiniLM-L6-v2 exported to ONNX). Skipped otherwise.
 */
#[Group('integration')]
#[CoversNothing]
final class AiEmbedIntegrationTest extends TestCase
{
    private string $modelDir = '';

    protected function setUp(): void
    {
        if (getenv('FERRY_AI_SKIP_NATIVE') === '1') {
            self::markTestSkipped('Native tests skipped via FERRY_AI_SKIP_NATIVE=1.');
        }

        if (!(new OnnxBackend(new NativeOnnxRuntime()))->isAvailable()) {
            self::markTestSkipped('ONNX Runtime shared library is not available.');
        }

        $this->modelDir = getenv('FERRY_AI_MODEL_DIR') ?: 'D:\\FerryAI\\all-MiniLM-L6-v2-onnx';

        if (!\is_file($this->modelDir . '/model.onnx') || !\is_file($this->modelDir . '/tokenizer.json')) {
            self::markTestSkipped('Embedding model dir not found: ' . $this->modelDir);
        }

        AI::reset();
        AI::config([
            'backend' => 'onnx',
            'backends' => ['embedding' => ['model_path' => $this->modelDir]],
        ]);
    }

    protected function tearDown(): void
    {
        AI::reset();
    }

    public function testEmbedReturnsNormalizedVector(): void
    {
        $result = AI::embed('Hello world');

        self::assertInstanceOf(EmbeddingResult::class, $result);
        self::assertSame(384, $result->dimension);
        self::assertCount(384, $result->vector);

        $norm = 0.0;

        foreach ($result->vector as $value) {
            $norm += $value * $value;
        }

        self::assertEqualsWithDelta(1.0, \sqrt($norm), 0.01);
    }

    public function testSimilarityIsSemantic(): void
    {
        $close = AI::similarity('cat', 'kitten');
        $far = AI::similarity('cat', 'airplane');

        self::assertGreaterThan($far, $close);
    }

    public function testEmbedBatchReturnsResultPerInput(): void
    {
        $results = AI::embed(['hello', 'world', 'foo']);

        self::assertIsArray($results);
        self::assertCount(3, $results);
        self::assertContainsOnlyInstancesOf(EmbeddingResult::class, $results);
    }
}
