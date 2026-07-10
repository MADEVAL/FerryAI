<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Exception\ConfigurationException;
use FerryAI\Core\RetryHandler;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: RetryHandler exponential backoff must be capped so a large
 * attempt count never produces INF (which casts to 0 ms — a catastrophic tight loop),
 * and the exhausted fallback must throw a {@see FerryAIException} subclass (AGENTS.md §5).
 */
#[CoversNothing]
final class RetryBackoffCapTest extends TestCase
{
    public function testExponentialBackoffIsCappedAt30Seconds(): void
    {
        self::assertSame(30_000, RetryHandler::backoffDelay('exponential', 60_000, 2));
        self::assertSame(30_000, RetryHandler::backoffDelay('exponential', 1000, 100));
    }

    public function testHugeAttemptDoesNotOverflowToZero(): void
    {
        // 2 ** 1023 overflows to INF; (int) INF === 0 -> zero-delay loop. Must clamp to the cap.
        self::assertSame(30_000, RetryHandler::backoffDelay('exponential', 1000, 1024));
    }

    public function testExponentialBackoffBelowCapGrows(): void
    {
        self::assertSame(1000, RetryHandler::backoffDelay('exponential', 1000, 1));
        self::assertSame(2000, RetryHandler::backoffDelay('exponential', 1000, 2));
        self::assertSame(4000, RetryHandler::backoffDelay('exponential', 1000, 3));
    }

    public function testLinearBackoffIsConstantAndCapped(): void
    {
        self::assertSame(1000, RetryHandler::backoffDelay('linear', 1000, 5));
        self::assertSame(30_000, RetryHandler::backoffDelay('linear', 90_000, 1));
    }

    public function testExhaustedFallbackThrowsFerryAIException(): void
    {
        $handler = new RetryHandler();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('maxAttempts');

        $handler->retry(static fn(): int => 42, 0);
    }
}
