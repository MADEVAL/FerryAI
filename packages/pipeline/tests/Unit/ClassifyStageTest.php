<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\ValueObjects\ClassificationResult;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\Pipeline\Stages\ClassifyStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClassifyStage::class)]
final class ClassifyStageTest extends TestCase
{
    public function testName(): void
    {
        $backend = new StubBackendForClassify();
        $stage = new ClassifyStage($backend, 'model-path');

        self::assertSame('classify', $stage->name());
    }

    public function testProcessReturnsClassificationResult(): void
    {
        $backend = new StubBackendForClassify();
        $stage = new ClassifyStage($backend, 'model-path');

        $result = $stage->process([0.1, 0.2]);

        self::assertInstanceOf(ClassificationResult::class, $result);
        self::assertSame(0.8, $result->confidence);
    }

    public function testProcessPopulatesAllScores(): void
    {
        $stage = new ClassifyStage(new StubBackendForClassify(), 'model-path');

        $result = $stage->process([0.1, 0.2]);

        self::assertSame('1', $result->label);
        self::assertSame(['0' => 0.3, '1' => 0.8, '2' => 0.1], $result->allScores);
    }

    public function testProcessUnwrapsTensorOutput(): void
    {
        $stage = new ClassifyStage(new StubBackendForClassifyTensor(), 'model-path');

        $result = $stage->process([0.1, 0.2]);

        self::assertSame('1', $result->label);
        self::assertSame(0.8, $result->confidence);
    }
}

final class StubBackendForClassifyTensor implements Backend
{
    public function availableDevices(): array
    {
        return [Device::CPU];
    }
    public function load(string $source, ?Device $device = null): Model
    {
        return new StubModelForClassifyTensor();
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

final class StubModelForClassifyTensor implements Model
{
    public function run(array $inputs): array
    {
        return ['output' => \FerryAI\Tensor\ArrayTensor::fromNested([0.3, 0.8, 0.1])];
    }
    public function inputs(): array
    {
        return ['input' => ['name' => 'input', 'shape' => [3], 'dtype' => 'float32']];
    }
    public function outputs(): array
    {
        return ['output' => ['name' => 'output', 'shape' => [3], 'dtype' => 'float32']];
    }
    public function metadata(): ModelMetadata
    {
        return new ModelMetadata('stub', '1', '', '', [], 0);
    }
    public function device(): Device
    {
        return Device::CPU;
    }
    public function unload(): void {}
}

final class StubBackendForClassify implements Backend
{
    public function availableDevices(): array
    {
        return [Device::CPU];
    }
    public function load(string $source, ?Device $device = null): Model
    {
        return new StubModelForClassify();
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

final class StubModelForClassify implements Model
{
    public function run(array $inputs): array
    {
        return ['output' => [0.3, 0.8, 0.1]];
    }
    public function inputs(): array
    {
        return ['input' => ['name' => 'input', 'shape' => [3], 'dtype' => 'float32']];
    }
    public function outputs(): array
    {
        return ['output' => ['name' => 'output', 'shape' => [3], 'dtype' => 'float32']];
    }
    public function metadata(): ModelMetadata
    {
        return new ModelMetadata('stub', '1', '', '', [], 0);
    }
    public function device(): Device
    {
        return Device::CPU;
    }
    public function unload(): void {}
}
