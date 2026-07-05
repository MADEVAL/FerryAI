<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Pipeline\Stages\FilterStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FilterStage::class)]
final class FilterStageTest extends TestCase
{
    public function testName(): void
    {
        $stage = new FilterStage(static fn(mixed $x): bool => true);
        self::assertSame('filter', $stage->name());
    }

    public function testProcessPassesThroughWhenPredicateTrue(): void
    {
        $stage = new FilterStage(static fn(mixed $x): bool => true);
        self::assertSame('data', $stage->process('data'));
    }

    public function testProcessReturnsNullWhenPredicateFalse(): void
    {
        $stage = new FilterStage(static fn(mixed $x): bool => false);
        self::assertNull($stage->process('data'));
    }
}
