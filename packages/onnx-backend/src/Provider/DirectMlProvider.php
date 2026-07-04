<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Provider;

use FerryAI\Core\Enums\Device;

/**
 * DirectML provider. Real support is planned and will require a dedicated FFI layer on top of
 * ONNX Runtime (phpmlkit does not expose DirectML); until then isAvailable() returns false.
 */
final class DirectMlProvider implements ExecutionProvider
{
    #[\Override]
    public function name(): string
    {
        return 'DmlExecutionProvider';
    }

    #[\Override]
    public function device(): Device
    {
        return Device::DIRECTML;
    }

    #[\Override]
    public function isAvailable(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function configure(): array
    {
        return [];
    }
}
