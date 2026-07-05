<?php

declare(strict_types=1);

namespace FerryAI\CpuBackend\Tests\Unit;

use FerryAI\Core\Contracts\Model;
use FerryAI\CpuBackend\CpuNativeModel;
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
        $model = new CpuNativeModel('test', []);

        $model->unload();

        $this->expectException(\RuntimeException::class);
        $model->run([]);
    }
}
