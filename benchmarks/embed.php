#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Profiler;

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: dirname(__DIR__) . '/models/all-MiniLM-L6-v2-onnx';
$modelPath = $modelDir . '/model.onnx';
$tokenizerPath = $modelDir . '/tokenizer.json';

if (!file_exists($modelPath)) {
    echo "SKIP: model not found at $modelPath\n";
    exit(0);
}

$backend = new FerryAI\OnnxBackend\OnnxBackend();
$tokenizer = (new FerryAI\Tokenizer\TokenizerFactory())->createFromFile($tokenizerPath);
$embedder = new FerryAI\Embedding\Embedder($modelPath, $backend, $tokenizer, 'mean', normalize: true);

$profiler = new Profiler();

echo "=== Embedding Benchmarks ===\n\n";

$warmup = 3;
$runs = 50;

for ($i = 0; $i < $warmup; $i++) {
    $embedder->embed("warmup $i");
}

$profiler->start('single');

for ($i = 0; $i < $runs; $i++) {
    $embedder->embed("bench text $i");
}
$profiler->end('single');

$texts = array_map(fn(int $i): string => "batch $i", range(1, 8));
$profiler->start('batch8');

for ($i = 0; $i < $runs; $i++) {
    $embedder->embedBatch($texts);
}
$profiler->end('batch8');

$profiler->start('similarity');

for ($i = 0; $i < $runs; $i++) {
    $embedder->cosineSimilarity(
        $embedder->embed("text $i"),
        $embedder->embed("compare $i"),
    );
}
$profiler->end('similarity');

$report = $profiler->report();

printf("%-15s %6s %10s %10s %10s\n", 'Test', 'Runs', 'Total(ms)', 'Avg(ms)', 'Vecs/s');
echo str_repeat('-', 55) . "\n";

foreach ($report as $label => $s) {
    $throughput = match ($label) {
        'batch8' => ($runs * 8) / ($s['total_ms'] / 1000),
        'similarity' => $runs / ($s['total_ms'] / 1000),
        default => $runs / ($s['total_ms'] / 1000),
    };
    printf("%-15s %6d %10.2f %10.2f %10.1f\n", $label, $s['count'], $s['total_ms'], $s['avg_ms'], $throughput);
}

echo "\nDone.\n";
