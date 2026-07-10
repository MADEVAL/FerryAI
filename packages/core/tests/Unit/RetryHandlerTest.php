<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit;

use FerryAI\Core\RetryHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryHandler::class)]
final class RetryHandlerTest extends TestCase
{
    public function testRetrySucceedsOnFirstAttempt(): void
    {
        $handler = new RetryHandler();
        $calls = 0;

        $result = $handler->retry(function () use (&$calls): int {
            $calls++;

            return 42;
        });

        self::assertSame(42, $result);
        self::assertSame(1, $calls);
    }

    public function testRetryAfterFailures(): void
    {
        $handler = new RetryHandler();
        $calls = 0;

        $result = $handler->retry(function () use (&$calls): int {
            $calls++;

            if ($calls < 3) {
                throw new \RuntimeException('temporary error');
            }

            return 99;
        }, 5, 10, 'linear');

        self::assertSame(99, $result);
        self::assertSame(3, $calls);
    }

    public function testRetryThrowsAfterMaxAttempts(): void
    {
        $handler = new RetryHandler();
        $calls = 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('temporary error');

        $handler->retry(function () use (&$calls): void {
            $calls++;

            throw new \RuntimeException('temporary error');
        }, 3, 10, 'linear');
    }

    public function testShouldRetryForRuntimeError(): void
    {
        self::assertTrue(RetryHandler::shouldRetry(new \RuntimeException('network error')));
    }

    public function testShouldNotRetryForModelLoadError(): void
    {
        self::assertFalse(RetryHandler::shouldRetry(
            new \FerryAI\Core\Exception\ModelLoadException('/path', 'invalid format'),
        ));
    }
}
