<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Exception\BackendNotAvailableException;

/**
 * Central registry of available backends, keyed by {@see BackendType}.
 */
final class BackendRegistry
{
    /** @var array<string, Backend> */
    private array $backends = [];

    /**
     * Auto-detection order: fastest/most-capable first.
     *
     * @var list<BackendType>
     */
    private const array AUTO_DETECT_ORDER = [
        BackendType::Llama,
        BackendType::Onnx,
        BackendType::CpuNative,
    ];

    public function register(BackendType $type, Backend $backend): void
    {
        $this->backends[$type->value] = $backend;
    }

    public function has(BackendType $type): bool
    {
        return isset($this->backends[$type->value]);
    }

    /**
     * @throws BackendNotAvailableException when the backend is not registered
     */
    public function get(BackendType $type): Backend
    {
        return $this->backends[$type->value]
            ?? throw new BackendNotAvailableException($type->value, 'backend is not registered');
    }

    /**
     * @return array<string, Backend>
     */
    public function all(): array
    {
        return $this->backends;
    }

    /**
     * Returns the best registered backend that is currently available.
     *
     * @throws BackendNotAvailableException when no registered backend is available
     */
    public function autoDetect(): BackendType
    {
        foreach (self::AUTO_DETECT_ORDER as $type) {
            if ($this->has($type) && $this->get($type)->isAvailable()) {
                return $type;
            }
        }

        throw new BackendNotAvailableException('auto', 'no registered backend is available');
    }
}
