<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\RetryHandler;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: RetryHandler must not burn retry attempts (with delays) on
 * unrecoverable failures. Invalid input (ValidationException) and programming errors
 * (\Error / \TypeError) can never succeed on a second attempt, so the callable must run once.
 */
#[CoversNothing]
final class RetryUnrecoverableTest extends TestCase
{
    public function testValidationExceptionIsNotRetried(): void
    {
        $handler = new RetryHandler();
        $calls = 0;

        try {
            $handler->retry(function () use (&$calls): void {
                $calls++;

                throw new ValidationException('bad input');
            }, 3, 0);
            self::fail('Expected ValidationException.');
        } catch (ValidationException) {
            // expected
        }

        self::assertSame(1, $calls);
    }

    public function testErrorIsNotRetried(): void
    {
        $handler = new RetryHandler();
        $calls = 0;

        try {
            $handler->retry(function () use (&$calls): int {
                $calls++;

                /** @phpstan-ignore-next-line intentional TypeError */
                return \strlen([]); // @phpstan-ignore-line
            }, 3, 0);
            self::fail('Expected TypeError.');
        } catch (\TypeError) {
            // expected
        }

        self::assertSame(1, $calls);
    }
}
