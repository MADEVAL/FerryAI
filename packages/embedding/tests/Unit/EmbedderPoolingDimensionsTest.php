<?php

declare(strict_types=1);

namespace FerryAI\Embedding\Tests\Unit;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\Embedding\Embedder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Regression: pooling must run even when the model emits 2D [seq, hidden] output
 * (no batch dimension), not just 3D [batch, seq, hidden].
 */
#[CoversClass(Embedder::class)]
final class EmbedderPoolingDimensionsTest extends TestCase
{
    public function testMeanPoolingAppliedFor2dModelOutput(): void
    {
        // 2D output: seq=2, hidden=2. Mean = [1.0, 2.0]; first token = [2.0, 0.0].
        $backend = new StubBackend2dOutput([[2.0, 0.0], [0.0, 4.0]]);
        $embedder = new Embedder('m', $backend, new StubTokenizerForEmbedder(), 'mean', false);

        $result = $embedder->embed('hello');

        self::assertEqualsWithDelta([1.0, 2.0], $result, 1e-9, 'pooling must not be bypassed for 2D output');
    }

    public function testMeanPoolingAppliedFor3dModelOutput(): void
    {
        // 3D output: batch=1, seq=2, hidden=2. Same expected mean.
        $backend = new StubBackend3dOutput([[[2.0, 0.0], [0.0, 4.0]]]);
        $embedder = new Embedder('m', $backend, new StubTokenizerForEmbedder(), 'mean', false);

        $result = $embedder->embed('hello');

        self::assertEqualsWithDelta([1.0, 2.0], $result, 1e-9);
    }
}

/**
 * @phpstan-type Matrix2d array<int, array<int, float>>
 */
final class StubBackend2dOutput implements Backend
{
    /** @param array<int, array<int, float>> $hidden */
    public function __construct(private array $hidden) {}

    public function availableDevices(): array
    {
        return [Device::CPU];
    }

    public function load(string $source, ?Device $device = null): Model
    {
        return new StubModelRawOutput($this->hidden, \count($this->hidden[0]));
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

final class StubBackend3dOutput implements Backend
{
    /** @param array<int, array<int, array<int, float>>> $hidden */
    public function __construct(private array $hidden) {}

    public function availableDevices(): array
    {
        return [Device::CPU];
    }

    public function load(string $source, ?Device $device = null): Model
    {
        return new StubModelRawOutput($this->hidden, \count($this->hidden[0][0]));
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

final class StubModelRawOutput implements Model
{
    /** @param array<mixed> $hidden */
    public function __construct(private array $hidden, private int $hiddenDim) {}

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
            'last_hidden_state' => ['name' => 'last_hidden_state', 'shape' => [1, -1, $this->hiddenDim], 'dtype' => 'float32'],
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
