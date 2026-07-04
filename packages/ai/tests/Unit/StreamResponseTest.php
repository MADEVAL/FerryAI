<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\StreamResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamResponse::class)]
final class StreamResponseTest extends TestCase
{
    public function testCreateIsNotImplementedInPhase1(): void
    {
        $this->expectException(\RuntimeException::class);

        StreamResponse::create([]);
    }

    public function testToSseIsNotImplementedInPhase1(): void
    {
        $this->expectException(\RuntimeException::class);

        (new StreamResponse([]))->toSse();
    }

    public function testToNdjsonIsNotImplementedInPhase1(): void
    {
        $this->expectException(\RuntimeException::class);

        (new StreamResponse([]))->toNdjson();
    }
}
