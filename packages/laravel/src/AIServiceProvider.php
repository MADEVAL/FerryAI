<?php

declare(strict_types=1);

namespace FerryAI\Laravel;

use FerryAI\AI;
use FerryAI\FrameworkConfig;

final class AIServiceProvider
{
    public function register(): void
    {
        AI::config($this->getConfig());
    }

    public function boot(): void
    {
        $warmup = $this->getConfig()['warmup'] ?? [];

        if ($warmup !== []) {
            AI::warmup($warmup);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return FrameworkConfig::defaults() + [
            'log_channel' => FrameworkConfig::env('FERRY_AI_LOG_CHANNEL', 'stack'),
        ];
    }
}
