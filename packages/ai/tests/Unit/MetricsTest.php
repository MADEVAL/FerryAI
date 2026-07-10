<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\Metrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Metrics::class)]
final class MetricsTest extends TestCase
{
    protected function setUp(): void
    {
        Metrics::reset();
    }

    public function testIncrement(): void
    {
        Metrics::increment('inference_count');
        Metrics::increment('inference_count');

        $report = Metrics::report();
        self::assertSame(2.0, $report['counters']['inference_count']['value']);
    }

    public function testRecord(): void
    {
        Metrics::record('memory_bytes', 1024.0);

        $report = Metrics::report();
        self::assertSame(1024.0, $report['gauges']['memory_bytes']['value']);
    }

    public function testTiming(): void
    {
        Metrics::timing('inference_duration', 15.5);
        Metrics::timing('inference_duration', 25.3);

        $report = Metrics::report();
        $timings = $report['timings']['inference_duration'];
        self::assertCount(2, $timings);
        self::assertSame(15.5, $timings[0]);
        self::assertSame(25.3, $timings[1]);
    }

    public function testIncrementWithTags(): void
    {
        Metrics::increment('requests', ['backend' => 'onnx', 'device' => 'cpu']);

        $report = Metrics::report();
        self::assertArrayHasKey('requests{backend=onnx,device=cpu}', $report['counters']);
    }

    public function testResetClearsAll(): void
    {
        Metrics::increment('test');
        Metrics::timing('test_timing', 10.0);
        Metrics::reset();

        $report = Metrics::report();
        self::assertSame([], $report['counters']);
        self::assertSame([], $report['timings']);
    }

    public function testIncrementAndRecordDoNotOverwriteEachOther(): void
    {
        Metrics::increment('requests');
        Metrics::increment('requests');
        Metrics::record('memory', 512.0);

        $report = Metrics::report();

        self::assertSame(
            2.0,
            $report['counters']['requests']['value'],
            'increment() counter must not be overwritten by record().',
        );
        self::assertSame(
            512.0,
            $report['gauges']['memory']['value'],
            'record() gauge must not be overwritten by increment().',
        );
    }
}
