<?php

declare(strict_types=1);

namespace FerryAI\Core;

final class RetryHandler
{
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

                /** @psalm-suppress InvalidOperand */
                $delay = $backoff === 'exponential'
                    ? (int) ($delayMs * (float) (2 ** ($attempt - 1)))
                    : $delayMs;

                \usleep($delay * 1000);
            }
        }

        throw $lastException ?? new \RuntimeException('Retry exhausted');
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
