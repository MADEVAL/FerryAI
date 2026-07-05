<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\AsyncInference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AsyncInference::class)]
final class AsyncInferenceTest extends TestCase
{
    public function testRunAsyncReturnsFiber(): void
    {
        $ai = new AsyncInference();
        $fiber = $ai->runAsync(static fn(): string => 'result');

        self::assertInstanceOf(\Fiber::class, $fiber);
    }

    public function testWaitReturnsResult(): void
    {
        $ai = new AsyncInference();
        $fiber = $ai->runAsync(static fn(): int => 42);

        $result = $ai->wait($fiber);

        self::assertSame(42, $result);
    }

    public function testRunParallelReturnsResults(): void
    {
        $ai = new AsyncInference();
        $tasks = [
            static fn(): int => 1,
            static fn(): int => 2,
            static fn(): int => 3,
        ];

        $results = $ai->runParallel($tasks);

        self::assertSame([1, 2, 3], $results);
    }

    public function testWaitThrowsOnTimeout(): void
    {
        $ai = new AsyncInference();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('timed out');

        $ai->wait($ai->runAsync(static function (): never {
            while (true) {
                \Fiber::suspend();
            }
        }), 10);
    }
}
