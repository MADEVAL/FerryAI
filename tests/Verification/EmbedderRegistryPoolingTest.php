<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\TokenizerType;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\Embedding\Embedder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: an Embedder for a model registered in EmbeddedModels must use the
 * registry's recommended pooling (e.g. bge-small-en -> cls) when the caller does not override it,
 * instead of silently defaulting to mean and producing degraded embeddings.
 *
 * Hidden states are chosen so cls (first token) and mean differ observably:
 *   rows = [[2,0],[0,4],[4,2]]  ->  cls = [2.0, 0.0], mean = [2.0, 2.0]
 */
#[CoversNothing]
final class EmbedderRegistryPoolingTest extends TestCase
{
    private const array HIDDEN = [[2.0, 0.0], [0.0, 4.0], [4.0, 2.0]];

    public function testRegisteredClsModelUsesClsPoolingByDefault(): void
    {
        $embedder = new Embedder('bge-small-en', new EmbStubBackend(self::HIDDEN), new EmbStubTokenizer(), normalize: false);

        self::assertEqualsWithDelta([2.0, 0.0], $embedder->embed('x'), 1e-9);
    }

    public function testExplicitPoolingOverridesRegistry(): void
    {
        $embedder = new Embedder('bge-small-en', new EmbStubBackend(self::HIDDEN), new EmbStubTokenizer(), 'mean', false);

        self::assertEqualsWithDelta([2.0, 2.0], $embedder->embed('x'), 1e-9);
    }

    public function testUnknownModelFallsBackToMean(): void
    {
        $embedder = new Embedder('not-in-registry', new EmbStubBackend(self::HIDDEN), new EmbStubTokenizer(), normalize: false);

        self::assertEqualsWithDelta([2.0, 2.0], $embedder->embed('x'), 1e-9);
    }
}

final class EmbStubBackend implements Backend
{
    /** @param array<int, array<int, float>> $hidden */
    public function __construct(private array $hidden) {}

    public function availableDevices(): array
    {
        return [Device::CPU];
    }

    public function load(string $source, ?Device $device = null): Model
    {
        return new EmbStubModel($this->hidden);
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

final class EmbStubModel implements Model
{
    /** @param array<int, array<int, float>> $hidden */
    public function __construct(private array $hidden) {}

    public function run(array $inputs): array
    {
        return ['last_hidden_state' => $this->hidden];
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
            'last_hidden_state' => ['name' => 'last_hidden_state', 'shape' => [1, -1, 2], 'dtype' => 'float32'],
        ];
    }

    public function metadata(): ModelMetadata
    {
        return new ModelMetadata(name: 's', version: '1', author: 's', license: 'MIT', tags: [], sizeBytes: 0);
    }

    public function device(): Device
    {
        return Device::CPU;
    }

    public function unload(): void {}
}

final class EmbStubTokenizer implements Tokenizer
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
        return ['input_ids' => [[101, 2023, 102]], 'attention_mask' => [[1, 1, 1]]];
    }

    public function vocabSize(): int
    {
        return 30522;
    }

    public function type(): TokenizerType
    {
        return TokenizerType::WordPiece;
    }

    public function specialTokenId(string $tokenName): ?int
    {
        return null;
    }

    public function specialTokens(): array
    {
        return [];
    }

    public function countTokens(string $text): int
    {
        return 3;
    }

    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array
    {
        return [$text];
    }
}
