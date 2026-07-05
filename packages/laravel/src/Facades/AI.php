<?php

declare(strict_types=1);

namespace FerryAI\Laravel\Facades;

use FerryAI\AI as FerryAI;

final class AI
{
    /**
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return FerryAI::$method(...$arguments);
    }
}
