<?php

declare(strict_types=1);

namespace FerryAI\Core;

final class RetryHandler
{
    public const int MAX_BACKOFF_MS = 30_000;

    /**
     * Retry a callable with configurable attempts and delay.
     *
     * @template T
     * @param  callable(): T $fn
     * @return T
     */
    public function retry(callable $fn, int $maxAttempts = 3, int $delayMs = 1000, string $backoff = 'exponential'): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                return $fn();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    break;
                }

                if (!self::shouldRetry($e)) {
                    break;
                }

                \usleep(self::backoffDelay($backoff, $delayMs, $attempt) * 1000);
            }
        }

        throw $lastException ?? new \FerryAI\Core\Exception\ConfigurationException(
            'maxAttempts',
            'must be at least 1',
        );
    }

    /**
     * Delay in milliseconds before the given attempt, clamped to {@see MAX_BACKOFF_MS} so an
     * unbounded exponential term cannot overflow to INF (which casts to 0 ms — a tight loop).
     */
    public static function backoffDelay(string $backoff, int $delayMs, int $attempt): int
    {
        $delay = $backoff === 'exponential'
            ? (float) $delayMs * (float) (2 ** ($attempt - 1))
            : (float) $delayMs;

        return (int) \min($delay, (float) self::MAX_BACKOFF_MS);
    }

    public static function shouldRetry(\Throwable $e): bool
    {
        if ($e instanceof \FerryAI\Core\Exception\ModelLoadException) {
            return false;
        }

        if ($e instanceof \FerryAI\Core\Exception\ShapeMismatchException) {
            return false;
        }

        if ($e instanceof \FerryAI\Core\Exception\ConfigurationException) {
            return false;
        }

        if ($e instanceof \FerryAI\Core\Exception\ModelNotFoundException) {
            return false;
        }

        if ($e instanceof \FerryAI\Core\Exception\ValidationException) {
            return false;
        }

        return true;
    }
}
