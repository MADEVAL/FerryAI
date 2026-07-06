<?php

declare(strict_types=1);

namespace FerryAI\Tests\Unit;

use FerryAI\Core\AIConfig;
use FerryAI\Metrics;
use FerryAI\Observability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Observability::class)]
final class ObservabilityTest extends TestCase
{
    protected function setUp(): void
    {
        Metrics::reset();
    }

    public function testDisabledByDefaultRunsWithoutRecording(): void
    {
        $observability = new Observability();

        $result = $observability->measure('embed', static fn(): string => 'value');

        self::assertSame('value', $result);
        self::assertSame([], Metrics::report()['counters']);
        self::assertSame([], Metrics::report()['timings']);
    }

    public function testMetricsRecordCountAndTiming(): void
    {
        $observability = new Observability(metrics: true);

        $observability->measure('embed', static fn(): int => 42);

        $report = Metrics::report();
        self::assertArrayHasKey('ai.operation.count{op=embed,status=ok}', $report['counters']);
        self::assertArrayHasKey('ai.operation.ms{op=embed}', $report['timings']);
    }

    public function testProfilingRecordsProfile(): void
    {
        $observability = new Observability(profiling: true);

        $observability->measure('classify', static fn(): bool => true);

        self::assertArrayHasKey('classify', $observability->profilerReport());
    }

    public function testLoggingWritesEntry(): void
    {
        $logFile = \sys_get_temp_dir() . '/ferry-observability-' . \uniqid() . '.log';
        $observability = new Observability(logging: true, logFile: $logFile);

        $observability->measure('similarity', static fn(): float => 0.9);

        self::assertFileExists($logFile);
        self::assertStringContainsString('similarity', (string) \file_get_contents($logFile));
        @\unlink($logFile);
    }

    public function testErrorPathRecordsAndRethrows(): void
    {
        $observability = new Observability(metrics: true);

        try {
            $observability->measure('predict', static function (): void {
                throw new \RuntimeException('boom');
            });
            self::fail('Exception was not rethrown');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertArrayHasKey('ai.operation.count{op=predict,status=error}', Metrics::report()['counters']);
    }

    public function testFromConfigReadsFlags(): void
    {
        $config = AIConfig::fromArray(['observability' => ['metrics' => true]]);

        $observability = Observability::fromConfig($config);
        $observability->measure('embed', static fn(): int => 1);

        self::assertArrayHasKey('ai.operation.count{op=embed,status=ok}', Metrics::report()['counters']);
    }
}
