<?php

declare(strict_types=1);

namespace FerryAI\Core;

use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\ConfigurationException;

/**
 * @implements \ArrayAccess<string, mixed>
 */
final class AIConfig implements \ArrayAccess
{
    /**
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'backend' => 'auto',
            'device' => 'auto',
            'model_cache' => sys_get_temp_dir() . '/ferry-ai-models',
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'stream_timeout' => 30,
            'verify_signatures' => true,
            'log_level' => 'warning',
            'backends' => [],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function __construct(private array $config) {}

    /**
     * Creates a configuration by merging the given array over the defaults.
     *
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(array_replace_recursive(self::defaults(), $config));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->config;
    }

    /**
     * Returns a value by key; supports dot notation for nested access.
     *
     * @param array|null|string $default
     *
     * @psalm-param ':memory:'|'all-MiniLM-L6-v2'|array|null $default
     */
    public function get(string $key, array|string|null $default = null): mixed
    {
        $value = $this->config;

        foreach (explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Returns a new instance with the value set; supports dot notation. Immutable.
     */
    public function set(string $key, mixed $value): self
    {
        $config = $this->config;
        $cursor = &$config;

        $segments = explode('.', $key);
        $last = array_key_last($segments);

        foreach ($segments as $index => $segment) {
            if ($index === $last) {
                $cursor[$segment] = $value;

                break;
            }

            if (!isset($cursor[$segment]) || !\is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        unset($cursor);

        return new self($config);
    }

    /**
     * Checks whether a key exists; supports dot notation.
     */
    public function has(string $key): bool
    {
        $value = $this->config;

        foreach (explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * @throws ConfigurationException when the backend value is unknown
     */
    public function backend(): BackendType
    {
        $value = (string) $this->get('backend');

        return match ($value) {
            'onnx' => BackendType::Onnx,
            'llama' => BackendType::Llama,
            'cpu', 'cpu_native', 'auto' => BackendType::CpuNative,
            default => throw new ConfigurationException('backend', \sprintf("unknown backend '%s'", $value)),
        };
    }

    /**
     * @throws ConfigurationException when the device value is unknown
     */
    public function device(): Device
    {
        $value = (string) $this->get('device');

        return Device::tryFrom($value)
            ?? throw new ConfigurationException('device', \sprintf("unknown device '%s'", $value));
    }

    public function modelCache(): string
    {
        return (string) $this->get('model_cache');
    }

    public function maxTokens(): int
    {
        return (int) $this->get('max_tokens');
    }

    public function temperature(): float
    {
        return (float) $this->get('temperature');
    }

    public function topP(): float
    {
        return (float) $this->get('top_p');
    }

    public function streamTimeout(): int
    {
        return (int) $this->get('stream_timeout');
    }

    public function verifySignatures(): bool
    {
        return (bool) $this->get('verify_signatures');
    }

    public function logLevel(): string
    {
        return (string) $this->get('log_level');
    }

    /**
     * @return array<string, mixed>
     */
    public function backendsConfig(): array
    {
        /** @var array<string, mixed> $backends */
        $backends = $this->get('backends', []);

        return $backends;
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->config[(string) $offset] = $value;
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->config[(string) $offset]);
    }
}
