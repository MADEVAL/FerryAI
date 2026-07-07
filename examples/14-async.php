#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AsyncInference;
use FerryAI\Embedding\Embedder;
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\Tokenizer\TokenizerFactory;

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: 'D:\FerryAI\all-MiniLM-L6-v2-onnx';
$modelPath = $modelDir . '/model.onnx';
$tokenizerPath = $modelDir . '/tokenizer.json';

if (!file_exists($modelPath)) {
    echo "=== SKIP: model not found at $modelPath ===\n";
    exit(0);
}

$backend = new OnnxBackend();
$tokenizer = (new TokenizerFactory())->createFromFile($tokenizerPath);
$embedder = new Embedder($modelPath, $backend, $tokenizer, 'mean', normalize: true);

$async = new AsyncInference();

echo "=== 14 — Async Inference (Fibers) ===\n\n";

echo "--- Async Embed ---\n\n";

$fiber = $async->runAsync(fn(): array => $embedder->embed('Hello async world'));
echo "Fiber created, doing other work...\n";
$start = microtime(true);

$result = $async->wait($fiber, timeoutMs: 5000);
$elapsed = (microtime(true) - $start) * 1000;
printf("Result dim:    %d\n", count($result));
printf("Result[0]:     %.4f\n", $result[0]);
printf("Elapsed:       %.1f ms\n\n", $elapsed);

echo "--- Fiber Suspend/Resume ---\n\n";

$fiber = $async->runAsync(function (): string {
    $parts = [];

    for ($i = 0; $i < 5; $i++) {
        $parts[] = chr(65 + $i);
        Fiber::suspend();
    }

    return implode('', $parts);
});

$result = $async->wait($fiber);
printf("Suspend/resume result: '%s'\n\n", $result);

echo "--- Parallel Tasks ---\n\n";

$start = microtime(true);
$results = $async->runParallel([
    fn(): string => 'task-1-done',
    fn(): string => 'task-2-done',
    fn(): string => 'task-3-done',
]);
$elapsed = (microtime(true) - $start) * 1000;
printf("results: %s\n", json_encode($results));
printf("elapsed: %.1f ms\n\n", $elapsed);

echo "--- Timeout ---\n\n";

$slowFiber = $async->runAsync(function (): never {
    while (true) {
        Fiber::suspend();
    }
});

try {
    $async->wait($slowFiber, timeoutMs: 10);
    echo "FAIL: should have timed out\n";
} catch (\RuntimeException $e) {
    printf("Timeout: %s\n\n", $e->getMessage());
}

echo "=== OK ===\n";
