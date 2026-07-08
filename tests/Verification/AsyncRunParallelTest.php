<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\AsyncInference;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: runParallel() must schedule tasks as Fibers so cooperatively
 * suspending tasks actually interleave (not run strictly one-after-another), while preserving
 * result order by key.
 */
#[CoversNothing]
final class AsyncRunParallelTest extends TestCase
{
    public function testSuspendingTasksInterleave(): void
    {
        $log = [];

        $make = static function (int $id) use (&$log): callable {
            return static function () use (&$log, $id): int {
                $log[] = "start{$id}";
                \Fiber::suspend();
                $log[] = "end{$id}";

                return $id * 10;
            };
        };

        $results = (new AsyncInference())->runParallel([$make(1), $make(2)]);

        // All tasks reach their first suspend before any resumes -> interleaved order.
        self::assertSame(['start1', 'start2', 'end1', 'end2'], $log);
        // Results keyed in original order.
        self::assertSame([10, 20], $results);
    }

    public function testNonSuspendingTasksStillReturnInOrder(): void
    {
        $results = (new AsyncInference())->runParallel([
            static fn(): int => 1,
            static fn(): int => 2,
            static fn(): int => 3,
        ]);

        self::assertSame([1, 2, 3], $results);
    }
}
