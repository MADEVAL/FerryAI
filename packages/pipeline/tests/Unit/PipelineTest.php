<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Tests\Unit;

use FerryAI\Pipeline\Pipeline;
use FerryAI\Pipeline\Stages\FilterStage;
use FerryAI\Pipeline\Stages\TransformStage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pipeline::class)]
final class PipelineTest extends TestCase
{
    public function testPipeReturnsSelf(): void
    {
        $pipeline = new Pipeline();
        $stage = new TransformStage(static fn(string $x): string => $x);
        self::assertSame($pipeline, $pipeline->pipe($stage));
    }

    public function testStagesReturnsAddedStages(): void
    {
        $pipeline = new Pipeline();
        $stage1 = new TransformStage(static fn(string $x): string => \strtoupper($x));
        $stage2 = new TransformStage(static fn(string $x): string => $x . '!');
        $pipeline->pipe($stage1)->pipe($stage2);
        self::assertCount(2, $pipeline->stages());
        self::assertSame([$stage1, $stage2], $pipeline->stages());
    }

    public function testRunWithSingleElement(): void
    {
        $pipeline = new Pipeline();
        $pipeline
            ->pipe(new TransformStage(static fn(string $x): string => \strtoupper($x)))
            ->pipe(new TransformStage(static fn(string $x): string => $x . '!'));
        $results = \iterator_to_array($pipeline->run('hello'));
        self::assertSame(['HELLO!'], $results);
    }

    public function testRunWithArray(): void
    {
        $pipeline = new Pipeline();
        $pipeline->pipe(new TransformStage(static fn(string $x): string => \strtoupper($x)));
        self::assertSame(['HELLO', 'WORLD'], \iterator_to_array($pipeline->run(['hello', 'world'])));
    }

    public function testRunWithGenerator(): void
    {
        $pipeline = new Pipeline();
        $pipeline->pipe(new TransformStage(static fn(int $x): int => $x * 2));
        $gen = (static function (): \Generator {
            yield 1;
            yield 2;
            yield 3;
        })();
        self::assertSame([2, 4, 6], \iterator_to_array($pipeline->run($gen)));
    }

    public function testRunSkipsFilteredElements(): void
    {
        $pipeline = new Pipeline();
        $pipeline
            ->pipe(new FilterStage(static fn(int $x): bool => $x > 5))
            ->pipe(new TransformStage(static fn(int $x): int => $x * 10));
        self::assertSame([80, 100], \iterator_to_array($pipeline->run([3, 8, 2, 10])));
    }

    public function testInvoke(): void
    {
        $pipeline = new Pipeline();
        $pipeline->pipe(new TransformStage(static fn(string $x): string => \strtoupper($x)));
        self::assertSame(['HELLO'], \iterator_to_array($pipeline('hello')));
    }

    public function testEmptyPipelineReturnsInputAsIs(): void
    {
        $pipeline = new Pipeline();
        self::assertSame(['raw'], \iterator_to_array($pipeline->run('raw')));
    }
}
