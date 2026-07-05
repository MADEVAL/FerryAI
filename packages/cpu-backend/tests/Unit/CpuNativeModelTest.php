<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend\Tests\Unit;

use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\CpuBackend\CpuNativeModel;
use FerryAI\CpuBackend\Predictor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CpuNativeModel::class)]
final class CpuNativeModelTest extends TestCase
{
    public function testImplementsModel(): void
    {
        $model = new CpuNativeModel('test', []);

        self::assertInstanceOf(Model::class, $model);
    }

    public function testDeviceReturnsCpu(): void
    {
        $model = new CpuNativeModel('test', []);

        self::assertSame(\FerryAI\Core\Enums\Device::CPU, $model->device());
    }

    public function testMetadata(): void
    {
        $model = new CpuNativeModel('test-model', ['type' => 'classifier']);

        $metadata = $model->metadata();
        self::assertSame('test-model', $metadata->name);
    }

    public function testInputsAndOutputs(): void
    {
        $model = new CpuNativeModel('test', ['features' => ['a', 'b']]);

        self::assertIsArray($model->inputs());
        self::assertIsArray($model->outputs());
    }

    public function testUnload(): void
    {
        $predictor = new class implements Predictor {
            #[\Override]
            public function isAvailable(): bool
            {
                return true;
            }
            #[\Override]
            public function predict(mixed $model, array $samples): array
            {
                return ['A'];
            }
            #[\Override]
            public function proba(mixed $model, array $samples): array
            {
                return [];
            }
        };

        $model = new CpuNativeModel('test', [], new \stdClass(), $predictor);
        $model->unload();

        $this->expectException(\RuntimeException::class);
        $model->run([]);
    }

    public function testRunDelegatesToPredictorWhenEstimatorPresent(): void
    {
        $predictor = new class implements Predictor {
            #[\Override]
            public function isAvailable(): bool
            {
                return true;
            }

            #[\Override]
            public function predict(mixed $model, array $samples): array
            {
                return \array_fill(0, \count($samples), 'label-A');
            }

            #[\Override]
            public function proba(mixed $model, array $samples): array
            {
                return [];
            }
        };

        $model = new CpuNativeModel('m.rbm', [], new \stdClass(), $predictor);

        $out = $model->run(['samples' => [[1.0, 2.0], [3.0, 4.0]]]);

        self::assertSame(['output' => ['label-A', 'label-A']], $out);
    }

    public function testRunFallsBackWithoutEstimator(): void
    {
        $model = new CpuNativeModel('m', []);

        $this->expectException(BackendNotAvailableException::class);
        $model->run(['x' => 1]);
    }
}
