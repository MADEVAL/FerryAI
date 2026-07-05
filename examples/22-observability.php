#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Core\Logger;
use FerryAI\Core\RetryHandler;
use FerryAI\CpuBackend\CpuNativeModel;
use FerryAI\Metrics;
use FerryAI\ModelPool;
use FerryAI\Observability;
use FerryAI\Profiler;
use FerryAI\SharedMemoryManager;

echo "=== 22 — Observability & Model Pool ===\n\n";

Metrics::reset();
Profiler::reset();

echo "--- Observability wrapper (metrics + profiling + logging) ---\n\n";

$logFile = sys_get_temp_dir() . '/ferry-observability-example.log';
@unlink($logFile);

$observability = new Observability(metrics: true, profiling: true, logging: true, logFile: $logFile);

$result = $observability->measure('demo.embed', static function (): string {
    usleep(2000);

    return 'vector-computed';
});

try {
    $observability->measure('demo.classify', static function (): never {
        throw new RuntimeException('model file missing');
    });
} catch (RuntimeException) {
    // recorded as an error metric below
}

printf("operation result:  %s\n", $result);

$report = Metrics::report();
printf("metric counters:   %s\n", implode(', ', array_keys($report['counters'])));
printf("timing series:     %s\n", implode(', ', array_keys($report['timings'])));

$profiles = Profiler::report();
foreach ($profiles as $label => $stats) {
    printf("  profile %-14s count=%d avg=%.2fms\n", $label, $stats['count'], $stats['avg_ms']);
}

printf("log lines written: %d (%s)\n\n", substr_count((string) file_get_contents($logFile), "\n"), basename($logFile));
@unlink($logFile);

echo "--- Model pool (cache + memory-bounded eviction) ---\n\n";

$pool = new ModelPool(maxMemoryBytes: 1500);
$pool->put('a', new CpuNativeModel('a', []), 1000);
$pool->put('b', new CpuNativeModel('b', []), 1000);

printf("size after 2 puts (limit 1500B): %d\n", $pool->size());
printf("oldest 'a' evicted:              %s\n", $pool->acquire('a') === null ? 'yes' : 'no');
printf("memory usage:                    %dB\n", $pool->memoryUsage());

$pool->warmup(['w1', 'w2'], static fn(string $id): CpuNativeModel => new CpuNativeModel($id, []));
printf("warmup loaded via loader:        w1=%s w2=%s\n\n", $pool->acquire('w1') !== null ? 'ok' : 'miss', $pool->acquire('w2') !== null ? 'ok' : 'miss');

echo "--- RetryHandler (exponential backoff) ---\n\n";

$attempts = 0;
$value = (new RetryHandler())->retry(
    function () use (&$attempts): string {
        $attempts++;

        if ($attempts < 3) {
            throw new RuntimeException('transient failure');
        }

        return 'succeeded';
    },
    maxAttempts: 5,
    delayMs: 0,
);
printf("retry result: %s after %d attempts\n\n", $value, $attempts);

echo "--- Shared memory (opt-in, cross-worker weights) ---\n\n";

$shm = new SharedMemoryManager();
printf("ext-shmop available: %s\n", $shm->isAvailable() ? 'yes' : 'no');
printf("pool wiring:         ModelPool(maxMemoryBytes, SharedMemory) — enable via config model_pool.shared_memory=true\n\n");

echo "--- Facade config (all channels off by default) ---\n\n";
echo "AI::config(['observability' => ['metrics' => true, 'profiling' => true, 'logging' => true]]);\n";
echo "→ embed()/chat()/classify()/... are then measured automatically.\n\n";

$logger = new Logger($logFile, 'warning');
$logger->info('suppressed by threshold');
$logger->error('recorded');
printf("logger level threshold honored: %s\n", str_contains((string) file_get_contents($logFile), 'suppressed') ? 'no' : 'yes');
@unlink($logFile);

echo "\n=== OK ===\n";
