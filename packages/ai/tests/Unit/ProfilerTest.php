<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\Profiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Profiler::class)]
final class ProfilerTest extends TestCase
{
    private Profiler $profiler;

    protected function setUp(): void
    {
        $this->profiler = new Profiler();
    }

    public function testStartEndReturnsDuration(): void
    {
        $this->profiler->start('test-op');
        \usleep(10000);
        $duration = $this->profiler->end('test-op');

        self::assertGreaterThan(0.0, $duration);
    }

    public function testReportReturnsStats(): void
    {
        $this->profiler->start('op');
        \usleep(5000);
        $this->profiler->end('op');

        $report = $this->profiler->report();

        self::assertArrayHasKey('op', $report);
        self::assertSame(1, $report['op']['count']);
        self::assertGreaterThan(0.0, $report['op']['total_ms']);
        self::assertGreaterThan(0.0, $report['op']['avg_ms']);
    }

    public function testMultipleCallsAccumulate(): void
    {
        $this->profiler->start('multiop');
        \usleep(5000);
        $this->profiler->end('multiop');
        $this->profiler->start('multiop');
        \usleep(5000);
        $this->profiler->end('multiop');

        $report = $this->profiler->report();

        self::assertSame(2, $report['multiop']['count']);
    }

    public function testResetClearsData(): void
    {
        $this->profiler->start('temp');
        $this->profiler->end('temp');
        $this->profiler->reset();

        $report = $this->profiler->report();
        self::assertSame([], $report);
    }

    public function testEndWithoutStartReturnsZeroDuration(): void
    {
        $duration = $this->profiler->end('never-started');

        self::assertSame(0.0, $duration);
    }

    public function testUnmatchedEndDoesNotSkewMinMs(): void
    {
        $this->profiler->end('op'); // no matching start(): must not record a bogus 0.0 sample
        $this->profiler->start('op');
        \usleep(3000);
        $this->profiler->end('op');

        $report = $this->profiler->report();

        self::assertSame(1, $report['op']['count']);
        self::assertGreaterThan(0.0, $report['op']['min_ms']);
    }

    public function testTwoInstancesDoNotShareState(): void
    {
        $a = new Profiler();
        $b = new Profiler();

        $a->start('a-op');
        $b->start('b-op');
        $a->end('a-op');
        $b->end('b-op');

        $reportA = $a->report();
        $reportB = $b->report();

        self::assertArrayHasKey('a-op', $reportA);
        self::assertArrayNotHasKey('b-op', $reportA, 'Profiler instances must be isolated.');
        self::assertArrayHasKey('b-op', $reportB);
        self::assertArrayNotHasKey('a-op', $reportB);
    }
}
