<?php

declare(strict_types=1);

namespace FerryAI\Symfony;

use FerryAI\AI;
use FerryAI\Symfony\DependencyInjection\FerryAIExtension;

final class AIBundle
{
    /**
     * @param array<int, array<string, mixed>> $configs configuration layers (as Symfony would pass)
     */
    public function boot(array $configs = []): void
    {
        $extension = new FerryAIExtension();
        $extension->load($configs);

        AI::config($extension->getConfig());
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        $extension = new FerryAIExtension();
        $extension->load([]);

        return $extension->getConfig();
    }
}
