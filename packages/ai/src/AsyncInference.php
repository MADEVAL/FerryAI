<?php

declare(strict_types=1);

namespace FerryAI;

use Fiber;

final class AsyncInference
{
    /**
     * @template T
     *
     * @param callable(): T $inference
     *
     *
     * @psalm-suppress TooManyTemplateParams
     *
     * @return Fiber
     * @phpstan-return Fiber<mixed, mixed, mixed, T>
     */
    public function runAsync(callable $inference): Fiber
    {
        return new Fiber(function () use ($inference): mixed {
            return $inference();
        });
    }

    /**
     * @phpstan-param Fiber<mixed, mixed, mixed, mixed> $fiber
     *
     * @psalm-suppress TooManyTemplateParams
     */
    public function wait(Fiber $fiber, int $timeoutMs = 30000): mixed
    {
        if ($fiber->isTerminated()) {
            return $fiber->getReturn();
        }

        $startTime = \microtime(true) * 1000.0;

        if (!$fiber->isStarted()) {
            $fiber->start();
        }

        /** @phpstan-ignore booleanNot.alwaysTrue */
        while (!$fiber->isTerminated()) {
            $elapsed = \microtime(true) * 1000.0 - $startTime;

            if ($elapsed >= $timeoutMs) {
                throw new \RuntimeException(\sprintf(
                    'Async inference timed out after %d ms',
                    $timeoutMs,
                ));
            }

            if ($fiber->isSuspended()) {
                $fiber->resume();
            } else {
                \usleep(1000);
            }
        }

        /** @phpstan-ignore deadCode.unreachable */
        return $fiber->getReturn();
    }

    /**
     * @param  array<int, callable(): mixed> $tasks
     * @return array<int, mixed>
     */
    public function runParallel(array $tasks): array
    {
        $results = [];

        foreach ($tasks as $i => $task) {
            $results[$i] = $task();
        }

        return $results;
    }
}
