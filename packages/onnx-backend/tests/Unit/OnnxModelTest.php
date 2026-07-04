<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Tests\Unit;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\Exception\InferenceException;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\Core\ValueObjects\Shape;
use FerryAI\OnnxBackend\OnnxModel;
use FerryAI\OnnxBackend\OnnxTensor;
use FerryAI\OnnxBackend\Tests\Double\MockOnnxRuntime;
use FerryAI\OnnxBackend\Tests\Double\MockOnnxSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnnxModel::class)]
final class OnnxModelTest extends TestCase
{
    private function session(): MockOnnxSession
    {
        return new MockOnnxSession(
            inputs: ['x' => ['name' => 'x', 'shape' => [1, 3], 'dtype' => 'float']],
            outputs: ['y' => ['name' => 'y', 'shape' => [1, 3], 'dtype' => 'float']],
            outputData: ['y' => ['data' => [[2.0, 4.0, 6.0]], 'shape' => [1, 3], 'dtype' => 'float']],
        );
    }

    private function model(MockOnnxRuntime $runtime, MockOnnxSession $session): OnnxModel
    {
        return new OnnxModel(
            $session,
            $runtime,
            new ModelMetadata('m', '1.0', '', 'MIT', [], 100),
            Device::CPU,
        );
    }

    public function testInputsAndOutputsMetadata(): void
    {
        $session = $this->session();
        $model = $this->model(new MockOnnxRuntime(session: $session), $session);

        self::assertArrayHasKey('x', $model->inputs());
        self::assertArrayHasKey('y', $model->outputs());
        self::assertSame([1, 3], $model->outputs()['y']['shape']);
    }

    public function testRunWrapsOutputsAsOnnxTensor(): void
    {
        $session = $this->session();
        $model = $this->model(new MockOnnxRuntime(session: $session), $session);

        $result = $model->run(['x' => [[1, 2, 3]]]);

        self::assertArrayHasKey('y', $result);
        self::assertInstanceOf(OnnxTensor::class, $result['y']);
        self::assertSame([[2.0, 4.0, 6.0]], $result['y']->toArray());
        self::assertSame(DType::Float32, $result['y']->dtype());
        self::assertEquals(new Shape([1, 3]), $result['y']->shape());
    }

    public function testRunAcceptsTensorInputs(): void
    {
        $session = $this->session();
        $runtime = new MockOnnxRuntime(session: $session);
        $model = $this->model($runtime, $session);

        $model->run(['x' => new OnnxTensor([[1.0, 2.0, 3.0]], new Shape([1, 3]), DType::Float32)]);

        self::assertSame([[1.0, 2.0, 3.0]], $runtime->runInputs[0]['x']);
    }

    public function testRunRejectsInvalidInput(): void
    {
        $session = $this->session();
        $model = $this->model(new MockOnnxRuntime(session: $session), $session);

        $this->expectException(InferenceException::class);

        $model->run(['x' => 'not-a-tensor']);
    }

    public function testRunAfterUnloadThrows(): void
    {
        $session = $this->session();
        $model = $this->model(new MockOnnxRuntime(session: $session), $session);
        $model->unload();

        $this->expectException(InferenceException::class);

        $model->run(['x' => [[1, 2, 3]]]);
    }

    public function testDeviceAndMetadata(): void
    {
        $session = $this->session();
        $model = $this->model(new MockOnnxRuntime(session: $session), $session);

        self::assertSame(Device::CPU, $model->device());
        self::assertSame('m', $model->metadata()->name);
    }
}
