<?php

declare(strict_types=1);

namespace FerryAI\Core\Enums;

use FerryAI\Core\Exception\DeviceNotAvailableException;

enum Device: string
{
    case CPU = 'cpu';
    case CUDA = 'cuda';
    case ROCM = 'rocm';
    case METAL = 'metal';
    case VULKAN = 'vulkan';
    case DIRECTML = 'directml';
    case OPENVINO = 'openvino';
    case OPENCL = 'opencl';
    case AUTO = 'auto';

    /**
     * Определяет лучшее доступное устройство из переданного списка.
     *
     * @param Device[] $available
     *
     * @throws DeviceNotAvailableException когда подходящее устройство недоступно
     */
    public static function resolve(self $preferred, array $available): self
    {
        if ($preferred !== self::AUTO) {
            if (\in_array($preferred, $available, true)) {
                return $preferred;
            }

            throw new DeviceNotAvailableException($preferred);
        }

        $best = null;

        foreach ($available as $device) {
            if ($best === null || $device->priority() > $best->priority()) {
                $best = $device;
            }
        }

        if ($best === null) {
            throw new DeviceNotAvailableException(self::AUTO);
        }

        return $best;
    }

    /**
     * Возвращает приоритет устройства (чем больше, тем лучше).
     */
    public function priority(): int
    {
        return match ($this) {
            self::CUDA => 90,
            self::ROCM => 80,
            self::METAL => 70,
            self::VULKAN => 60,
            self::DIRECTML => 50,
            self::OPENVINO => 40,
            self::OPENCL => 30,
            self::CPU => 10,
            self::AUTO => 0,
        };
    }
}
