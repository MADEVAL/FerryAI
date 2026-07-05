<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Tests\Unit;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\Embedding\Embedder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Embedder::class)]
final class EmbedderTest extends TestCase
{
    public function testModelNameReturnsGivenName(): void
    {
        $backend = new StubBackendForEmbedder(384);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('all-MiniLM-L6-v2', $backend, $tokenizer);

        self::assertSame('all-MiniLM-L6-v2', $embedder->modelName());
    }

    public function testDimensionReturnsModelDimension(): void
    {
        $backend = new StubBackendForEmbedder(512);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test-model', $backend, $tokenizer);

        self::assertSame(512, $embedder->dimension());
    }

    public function testNormalizeReturnsUnitVector(): void
    {
        $backend = new StubBackendForEmbedder(2);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer);

        $result = $embedder->normalize([3.0, 4.0]);

        self::assertEqualsWithDelta(0.6, $result[0], 0.0001);
        self::assertEqualsWithDelta(0.8, $result[1], 0.0001);
    }

    public function testNormalizeReturnsZerosForZeroVector(): void
    {
        $backend = new StubBackendForEmbedder(3);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer);

        $result = $embedder->normalize([0.0, 0.0, 0.0]);

        self::assertSame([0.0, 0.0, 0.0], $result);
    }

    public function testCosineSimilarityForIdenticalVectors(): void
    {
        $backend = new StubBackendForEmbedder(2);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer);

        $vec = [1.0, 2.0];

        self::assertEqualsWithDelta(1.0, $embedder->cosineSimilarity($vec, $vec), 0.0001);
    }

    public function testCosineSimilarityForOrthogonalVectors(): void
    {
        $backend = new StubBackendForEmbedder(2);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer);

        self::assertEqualsWithDelta(0.0, $embedder->cosineSimilarity([1.0, 0.0], [0.0, 1.0]), 0.0001);
    }

    public function testCosineSimilarityForOppositeVectors(): void
    {
        $backend = new StubBackendForEmbedder(2);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer);

        self::assertEqualsWithDelta(-1.0, $embedder->cosineSimilarity([1.0, 0.0], [-1.0, 0.0]), 0.0001);
    }

    public function testEmbedReturnsNormalizedVector(): void
    {
        $backend = new StubBackendForEmbedder(2);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer);

        $result = $embedder->embed('hello');

        self::assertCount(2, $result);
        $magnitude = \sqrt(\array_sum(\array_map(static fn(float $v): float => $v * $v, $result)));
        self::assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    public function testEmbedWithoutNormalization(): void
    {
        $backend = new StubBackendForEmbedder(2);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer, 'mean', false);

        $result = $embedder->embed('hello');

        self::assertCount(2, $result);
    }

    public function testEmbedBatchReturnsMultipleVectors(): void
    {
        $backend = new StubBackendForEmbedder(2);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer);

        $results = $embedder->embedBatch(['a', 'b', 'c']);

        self::assertCount(3, $results);

        foreach ($results as $vector) {
            self::assertCount(2, $vector);
        }
    }

    public function testCustomPoolingStrategy(): void
    {
        $backend = new StubBackendForEmbedder(3);
        $tokenizer = new StubTokenizerForEmbedder();
        $embedder = new Embedder('test', $backend, $tokenizer, 'cls', false);

        $result = $embedder->embed('text');

        self::assertCount(3, $result);
    }
}

final class StubBackendForEmbedder implements Backend
{
    /** @var array<string, mixed> */
    private array $output;

    public function __construct(int $dimension)
    {
        $this->output = [];

        for ($i = 0; $i < $dimension; $i++) {
            $this->output[] = 0.1 * ($i + 1);
        }
    }

    public function availableDevices(): array
    {
        return [Device::CPU];
    }

    public function load(string $source, ?Device $device = null): Model
    {
        return new StubModelForEmbedder($this->output);
    }

    public function version(): string
    {
        return 'stub';
    }

    public function isAvailable(): bool
    {
        return true;
    }
}

final class StubModelForEmbedder implements Model
{
    /** @var array<string, mixed> */
    private array $outputValue;

    private bool $unloaded = false;

    /** @param array<int, float> $output */
    public function __construct(private array $output)
    {
        $seqLen = 3;
        $hiddenDim = \count($output);
        $hiddenStates = [];

        for ($i = 0; $i < $seqLen; $i++) {
            $hiddenStates[] = $output;
        }
        $this->outputValue = ['last_hidden_state' => $hiddenStates];
    }

    public function run(array $inputs): array
    {
        if ($this->unloaded) {
            throw new \RuntimeException('Model is unloaded');
        }

        return $this->outputValue;
    }

    public function inputs(): array
    {
        return [
            'input_ids' => ['name' => 'input_ids', 'shape' => [1, 128], 'dtype' => 'int64'],
            'attention_mask' => ['name' => 'attention_mask', 'shape' => [1, 128], 'dtype' => 'int64'],
        ];
    }

    public function outputs(): array
    {
        return [
            'last_hidden_state' => [
                'name' => 'last_hidden_state',
                'shape' => [1, -1, \count($this->output)],
                'dtype' => 'float32',
            ],
        ];
    }

    public function metadata(): ModelMetadata
    {
        return new ModelMetadata(
            name: 'stub-model',
            version: '1.0',
            author: 'stub',
            license: 'MIT',
            tags: [],
            sizeBytes: 0,
        );
    }

    public function device(): Device
    {
        return Device::CPU;
    }

    public function unload(): void
    {
        $this->unloaded = true;
    }
}

final class StubTokenizerForEmbedder implements Tokenizer
{
    public function encode(string $text, bool $addSpecialTokens = true): array
    {
        return [101, 2023, 102];
    }

    public function decode(array $ids): string
    {
        return '';
    }

    public function encodeBatch(array $texts, bool $padToMaxLength = true): array
    {
        $batchSize = \count($texts);
        $inputIds = [];
        $attentionMask = [];

        for ($i = 0; $i < $batchSize; $i++) {
            $inputIds[] = [101, 2023, 102];
            $attentionMask[] = [1, 1, 1];
        }

        return [
            'input_ids' => $inputIds,
            'attention_mask' => $attentionMask,
        ];
    }

    public function vocabSize(): int
    {
        return 30522;
    }

    public function type(): \FerryAI\Core\Enums\TokenizerType
    {
        return \FerryAI\Core\Enums\TokenizerType::WordPiece;
    }

    public function specialTokenId(string $tokenName): ?int
    {
        return match ($tokenName) {
            'cls' => 101,
            'sep' => 102,
            'pad' => 0,
            default => null,
        };
    }

    public function specialTokens(): array
    {
        return [
            'bos' => 101,
            'eos' => 102,
            'pad' => 0,
        ];
    }

    public function countTokens(string $text): int
    {
        return \substr_count($text, ' ') + 1;
    }

    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array
    {
        return [$text];
    }
}
