<?php

declare(strict_types=1);

namespace FerryAI\Symfony\DependencyInjection;

final class FerryAIExtension
{
    /** @var array<string, mixed> */
    private array $config = [];

    /**
     * @param array<string, mixed> $configs
     */
    public function load(array $configs): void
    {
        $this->config = \array_merge($this->getDefaultConfig(), ...$configs);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            'backend' => 'auto',
            'device' => 'auto',
            'model_cache' => \sys_get_temp_dir() . '/ferry-ai-models',
            'max_tokens' => 2048,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'verify_signatures' => true,
        ];
    }
}
