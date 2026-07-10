<?php

declare(strict_types=1);

namespace FerryAI\Symfony\DependencyInjection;

use FerryAI\FrameworkConfig;

final class FerryAIExtension
{
    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * Merges the given configuration layers over the defaults. Deep merge so nested keys
     * (e.g. backends.llama.model_path) can be overridden individually.
     *
     * @param array<int, array<string, mixed>> $configs
     */
    public function load(array $configs): void
    {
        $this->config = \array_replace_recursive(FrameworkConfig::defaults(), ...$configs);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
