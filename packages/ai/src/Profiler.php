<?php

declare(strict_types=1);

namespace FerryAI;

final class Profiler
{
    /** @var array<string, float> */
    private array $startTimes = [];

    /** @var array<string, array{count: int, total_ms: float, min_ms: float, max_ms: float}> */
    private array $profiles = [];

    public function start(string $label): void
    {
        $this->startTimes[$label] = \microtime(true) * 1000.0;
    }

    public function end(string $label): float
    {
        if (!isset($this->startTimes[$label])) {
            return 0.0;
        }

        $endTime = \microtime(true) * 1000.0;
        $startTime = $this->startTimes[$label];
        $duration = $endTime - $startTime;

        if (!isset($this->profiles[$label])) {
            $this->profiles[$label] = [
                'count' => 0,
                'total_ms' => 0.0,
                'min_ms' => $duration,
                'max_ms' => $duration,
            ];
        }

        $this->profiles[$label]['count']++;
        $this->profiles[$label]['total_ms'] += $duration;
        $this->profiles[$label]['min_ms'] = \min($this->profiles[$label]['min_ms'], $duration);
        $this->profiles[$label]['max_ms'] = \max($this->profiles[$label]['max_ms'], $duration);

        unset($this->startTimes[$label]);

        return $duration;
    }

    /**
     * @return array<string, array{count: int, total_ms: float, avg_ms: float, min_ms: float, max_ms: float}>
     */
    public function report(): array
    {
        $result = [];

        foreach ($this->profiles as $label => $profile) {
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

    public function reset(): void
    {
        $this->startTimes = [];
        $this->profiles = [];
    }
}
