<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Pipeline\Stages\NormalizeStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NormalizeStage::class)]
final class NormalizeStageTest extends TestCase
{
    public function testName(): void
    {
        $stage = new NormalizeStage();
        self::assertSame('normalize', $stage->name());
    }

    public function testProcessNormalizesVector(): void
    {
        $stage = new NormalizeStage();
        $result = $stage->process([3.0, 4.0]);
        self::assertEqualsWithDelta(0.6, $result[0], 0.0001);
        self::assertEqualsWithDelta(0.8, $result[1], 0.0001);
    }

    public function testProcessReturnsSameForZeroVector(): void
    {
        $stage = new NormalizeStage();
        self::assertSame([0.0, 0.0], $stage->process([0.0, 0.0]));
    }
}
