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

    public function testRunEnforcesTimeout(): void
    {
        $pipeline = new FiberPipeline();
        $pipeline->pipe(new TransformStage(static function (string $x): string {
            \usleep(5000);

            return $x;
        }));
        $pipeline->setTimeout(0.001);

        $this->expectException(\FerryAI\Core\Exception\InferenceException::class);
        \iterator_to_array($pipeline->run('hello'));
    }

    public function testRunWithoutTimeoutCompletes(): void
    {
        $pipeline = new FiberPipeline();
        $pipeline->pipe(new TransformStage(static fn(string $x): string => \strtoupper($x)));
        $pipeline->setTimeout(10.0);

        self::assertSame(['HELLO'], \iterator_to_array($pipeline->run('hello')));
    }

    public function testExtendsPipeline(): void
    {
        self::assertInstanceOf(\FerryAI\Pipeline\Pipeline::class, new FiberPipeline());
    }
}
