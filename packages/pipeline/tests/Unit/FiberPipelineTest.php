<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Pipeline\FiberPipeline;
use FerryAI\Pipeline\Stages\TransformStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FiberPipeline::class)]
final class FiberPipelineTest extends TestCase
{
    public function testSetTimeoutReturnsSelf(): void
    {
        $pipeline = new FiberPipeline();

        $result = $pipeline->setTimeout(5.0);

        self::assertSame($pipeline, $result);
    }

    public function testRunAsyncReturnsFiber(): void
    {
        $pipeline = new FiberPipeline();
        $pipeline->pipe(new TransformStage(static fn(string $x): string => \strtoupper($x)));

        $fiber = $pipeline->runAsync('hello');

        self::assertInstanceOf(\Fiber::class, $fiber);
    }

    public function testRunReturnsGenerator(): void
    {
        $pipeline = new FiberPipeline();
        $pipeline->pipe(new TransformStage(static fn(string $x): string => \strtoupper($x)));

        $results = \iterator_to_array($pipeline->run('hello'));

        self::assertSame(['HELLO'], $results);
    }

    public function testExtendsPipeline(): void
    {
        self::assertInstanceOf(\FerryAI\Pipeline\Pipeline::class, new FiberPipeline());
    }
}
