<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\Profiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Profiler::class)]
final class ProfilerTest extends TestCase
{
    protected function setUp(): void
    {
        Profiler::reset();
    }

    public function testStartEndReturnsDuration(): void
    {
        Profiler::start('test-op');
        \usleep(10000);
        $duration = Profiler::end('test-op');

        self::assertGreaterThan(0.0, $duration);
    }

    public function testReportReturnsStats(): void
    {
        Profiler::start('op');
        \usleep(5000);
        Profiler::end('op');

        $report = Profiler::report();

        self::assertArrayHasKey('op', $report);
        self::assertSame(1, $report['op']['count']);
        self::assertGreaterThan(0.0, $report['op']['total_ms']);
        self::assertGreaterThan(0.0, $report['op']['avg_ms']);
    }

    public function testMultipleCallsAccumulate(): void
    {
        Profiler::start('multiop');
        \usleep(5000);
        Profiler::end('multiop');
        Profiler::start('multiop');
        \usleep(5000);
        Profiler::end('multiop');

        $report = Profiler::report();

        self::assertSame(2, $report['multiop']['count']);
    }

    public function testResetClearsData(): void
    {
        Profiler::start('temp');
        Profiler::end('temp');
        Profiler::reset();

        $report = Profiler::report();
        self::assertSame([], $report);
    }

    public function testEndWithoutStartReturnsZeroDuration(): void
    {
        $duration = Profiler::end('never-started');

        self::assertSame(0.0, $duration);
    }

    public function testUnmatchedEndDoesNotSkewMinMs(): void
    {
        Profiler::end('op'); // no matching start(): must not record a bogus 0.0 sample
        Profiler::start('op');
        \usleep(3000);
        Profiler::end('op');

        $report = Profiler::report();

        self::assertSame(1, $report['op']['count']);
        self::assertGreaterThan(0.0, $report['op']['min_ms']);
    }
}
