<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

use FerryAI\Core\Enums\Device;

class DeviceNotAvailableException extends FerryAIException
{
    public function __construct(private readonly Device $requested)
    {
        parent::__construct(\sprintf(
            "Device '%s' is not available. Verify the required drivers/runtime are installed, "
            . 'or use Device::AUTO to select the best available device.',
            $requested->value,
        ));
    }

    #[\Override]
    public function errorCode(): string
    {
        return 'FERRY_AI_DEVICE_NOT_AVAILABLE';
    }

    public function requestedDevice(): Device
    {
        return $this->requested;
    }
}
