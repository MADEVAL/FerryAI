<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Pipeline\Stages\TransformStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransformStage::class)]
final class TransformStageTest extends TestCase
{
    public function testName(): void
    {
        $stage = new TransformStage(static fn(string $x): string => $x);
        self::assertSame('transform', $stage->name());
    }

    public function testCustomName(): void
    {
        $stage = new TransformStage(static fn(string $x): string => $x, 'my-transform');
        self::assertSame('my-transform', $stage->name());
    }

    public function testProcessAppliesTransform(): void
    {
        $stage = new TransformStage(static fn(string $x): string => \strtoupper($x));
        self::assertSame('HELLO', $stage->process('hello'));
    }

    public function testProcessWithInt(): void
    {
        $stage = new TransformStage(static fn(int $x): int => $x * 2);
        self::assertSame(20, $stage->process(10));
    }
}
