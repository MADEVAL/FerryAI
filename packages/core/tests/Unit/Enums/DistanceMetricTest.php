<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Enums;

use FerryAI\Core\Enums\DistanceMetric;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DistanceMetric::class)]
final class DistanceMetricTest extends TestCase
{
    public function testAllThreeCasesAreDefined(): void
    {
        self::assertCount(3, DistanceMetric::cases());
    }

    public function testBackingValues(): void
    {
        self::assertSame('cosine', DistanceMetric::COSINE->value);
        self::assertSame('euclidean', DistanceMetric::EUCLIDEAN->value);
        self::assertSame('dot', DistanceMetric::DOT->value);
    }
}
