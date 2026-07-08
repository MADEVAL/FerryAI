<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\Exception\InferenceException;
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
                throw new InferenceException(\sprintf(
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
     * Runs the tasks concurrently as cooperative Fibers: each is started, then suspended fibers
     * are resumed round-robin until all terminate. Tasks that never call {@see Fiber::suspend()}
     * simply run to completion on start. Results are keyed in the original task order.
     *
     * @param  array<int, callable(): mixed> $tasks
     * @return array<int, mixed>
     */
    public function runParallel(array $tasks): array
    {
        $fibers = [];

        foreach ($tasks as $i => $task) {
            $fibers[$i] = new Fiber($task);
        }

        foreach ($fibers as $fiber) {
            $fiber->start();
        }

        do {
            $active = false;

            foreach ($fibers as $fiber) {
                if ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                if (!$fiber->isTerminated()) {
                    $active = true;
                }
            }
        } while ($active);

        $results = [];

        foreach ($fibers as $i => $fiber) {
            $results[$i] = $fiber->getReturn();
        }

        return $results;
    }
}
