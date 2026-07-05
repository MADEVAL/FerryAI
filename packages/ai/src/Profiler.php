<?php

declare(strict_types=1);

namespace FerryAI;

final class Profiler
{
    /** @var array<string, float> */
    private static array $startTimes = [];

    /** @var array<string, array{count: int, total_ms: float, min_ms: float, max_ms: float}> */
    private static array $profiles = [];

    public static function start(string $label): void
    {
        self::$startTimes[$label] = \microtime(true) * 1000.0;
    }

    public static function end(string $label): float
    {
        $endTime = \microtime(true) * 1000.0;
        $startTime = self::$startTimes[$label] ?? $endTime;
        $duration = $endTime - $startTime;

        if (!isset(self::$profiles[$label])) {
            self::$profiles[$label] = [
                'count' => 0,
                'total_ms' => 0.0,
                'min_ms' => $duration,
                'max_ms' => $duration,
            ];
        }

        self::$profiles[$label]['count']++;
        self::$profiles[$label]['total_ms'] += $duration;
        self::$profiles[$label]['min_ms'] = \min(self::$profiles[$label]['min_ms'], $duration);
        self::$profiles[$label]['max_ms'] = \max(self::$profiles[$label]['max_ms'], $duration);

        unset(self::$startTimes[$label]);

        return $duration;
    }

    /**
     * @return array<string, array{count: int, total_ms: float, avg_ms: float, min_ms: float, max_ms: float}>
     */
    public static function report(): array
    {
        $result = [];

        foreach (self::$profiles as $label => $profile) {
            $result[$label] = [
                'count' => $profile['count'],
                'total_ms' => $profile['total_ms'],
                'avg_ms' => $profile['count'] > 0 ? $profile['total_ms'] / (float) $profile['count'] : 0.0,
                'min_ms' => $profile['min_ms'],
                'max_ms' => $profile['max_ms'],
            ];
        }

        return $result;
    }

    public static function reset(): void
    {
        self::$startTimes = [];
        self::$profiles = [];
    }
}
