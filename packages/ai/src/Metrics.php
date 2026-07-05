<?php

declare(strict_types=1);

namespace FerryAI;

final class Metrics
{
    /** @var array<string, array<string, float>> */
    private static array $counters = [];

    /** @var array<string, array<int, float>> */
    private static array $timings = [];

    public static function increment(string $metric, array $tags = []): void
    {
        $key = self::buildKey($metric, $tags);
        self::$counters[$key]['value'] = (float) (self::$counters[$key]['value'] ?? 0.0) + 1.0;
    }

    public static function record(string $metric, float $value, array $tags = []): void
    {
        $key = self::buildKey($metric, $tags);
        self::$counters[$key]['value'] = $value;
    }

    public static function timing(string $metric, float $durationMs, array $tags = []): void
    {
        $key = self::buildKey($metric, $tags);
        self::$timings[$key][] = $durationMs;
    }

    /**
     * @return array<string, mixed>
     */
    public static function report(): array
    {
        return [
            'counters' => self::$counters,
            'timings' => self::$timings,
        ];
    }

    public static function reset(): void
    {
        self::$counters = [];
        self::$timings = [];
    }

    /**
     * @param array<string, string> $tags
     */
    private static function buildKey(string $metric, array $tags): string
    {
        if ($tags === []) {
            return $metric;
        }

        \ksort($tags);
        $tagStr = \implode(',', \array_map(
            static fn(string $k, string $v): string => $k . '=' . $v,
            \array_keys($tags),
            \array_values($tags),
        ));

        return $metric . '{' . $tagStr . '}';
    }
}
