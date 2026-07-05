#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\ModelPool;
use FerryAI\SharedMemoryManager;
use FerryAI\CpuBackend\CpuNativeModel;

echo "=== 15 — Model Pool & Shared Memory ===\n\n";

echo "--- ModelPool ---\n\n";

$pool = new ModelPool(maxMemoryBytes: 10_000_000);

$m1 = new CpuNativeModel('model-a', ['type' => 'classifier']);
$m2 = new CpuNativeModel('model-b', ['type' => 'regressor']);

$pool->put('classifier', $m1, memoryBytes: 5_000_000);
$pool->put('regressor', $m2, memoryBytes: 3_000_000);

printf("pool size:     %d\n", $pool->size());
printf("memory usage:  %d bytes (%.1f MB)\n", $pool->memoryUsage(), $pool->memoryUsage() / 1_000_000);

$acquired = $pool->acquire('classifier');
printf("acquired:      %s\n", $acquired !== null ? 'YES' : 'NO');

$pool->release('classifier');
printf("after release: size=%d\n\n", $pool->size());

$pool->evict('classifier');
printf("after evict:   size=%d\n", $pool->size());

$pool->warmup(['classifier', 'regressor', 'unknown']);

echo "\n--- SharedMemoryManager ---\n\n";

$shm = new SharedMemoryManager();
printf("shmop loaded:  %s\n", extension_loaded('shmop') ? 'YES' : 'NO');
printf("isAvailable:   %s\n\n", $shm->isAvailable() ? 'YES' : 'NO');

if ($shm->isAvailable()) {
    $testFile = sys_get_temp_dir() . '/ferry-shm-test.bin';
    file_put_contents($testFile, 'FerryAI shared memory test data');

    try {
        $key = $shm->allocateModel('test-model', $testFile);
        printf("allocate:      key=%d\n", $key);
        printf("isShared:      %s\n", $shm->isShared('test-model') ? 'YES' : 'NO');

        $shm->detachModel('test-model');
        printf("after detach:  isShared=%s\n", $shm->isShared('test-model') ? 'YES' : 'NO');
    } catch (\Throwable $e) {
        printf("ERROR: %s\n", $e->getMessage());
    }

    unlink($testFile);
}

echo "\n=== OK ===\n";
