#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Embedding\Embedder;
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\Profiler;
use FerryAI\Tokenizer\TokenizerFactory;
use FerryAI\Vector\CollectionManager;
use FerryAI\Vector\SQLiteStore;

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: dirname(__DIR__) . '/models/all-MiniLM-L6-v2-onnx';
$modelPath = $modelDir . '/model.onnx';
$tokenizerPath = $modelDir . '/tokenizer.json';

if (!file_exists($modelPath)) {
    echo "=== SKIP: model not found at $modelPath ===\n";
    exit(0);
}

$backend = new OnnxBackend();
$tokenizer = (new TokenizerFactory())->createFromFile($tokenizerPath);
$embedder = new Embedder($modelPath, $backend, $tokenizer, 'mean', normalize: true);

$profiler = new Profiler();

echo "=== 17 — Benchmark ===\n\n";

$warmup = 3;
$runs = 20;

echo "Warmup: $warmup iterations...\n";

for ($i = 0; $i < $warmup; $i++) {
    $embedder->embed("warmup $i");
}

echo "\n--- Single Embedding ---\n\n";

$profiler->start('embed.single');

for ($i = 0; $i < $runs; $i++) {
    $embedder->embed("benchmark text $i");
}
$profiler->end('embed.single');

echo "--- Batch Embedding ---\n\n";

$texts = array_map(fn(int $i): string => "batch text $i", range(1, 8));

$profiler->start('embed.batch');

for ($i = 0; $i < $runs; $i++) {
    $embedder->embedBatch($texts);
}
$profiler->end('embed.batch');

echo "--- Similarity ---\n\n";

$profiler->start('similarity');

for ($i = 0; $i < $runs; $i++) {
    $embedder->cosineSimilarity(
        $embedder->embed("similarity test $i"),
        $embedder->embed("comparison text $i"),
    );
}
$profiler->end('similarity');

echo "--- Vector Store ---\n\n";

$store = new SQLiteStore(':memory:');
$manager = new CollectionManager($store);
$collection = $manager->create('bench', 384);

$testVectors = array_map(fn(): array => array_map(fn(): float => mt_rand() / mt_getrandmax(), range(1, 384)), range(1, 50));
$profiler->start('vector.add');

foreach ($testVectors as $i => $v) {
    $collection->add("vec-$i", $v);
}
$profiler->end('vector.add');

$profiler->start('vector.search');

for ($i = 0; $i < $runs; $i++) {
    $collection->search($testVectors[$i % count($testVectors)], 10);
}
$profiler->end('vector.search');

echo "--- Results ---\n\n";

$report = $profiler->report();

printf("%-20s %6s %10s %10s %10s %10s\n", 'Operation', 'Runs', 'Total(ms)', 'Avg(ms)', 'Min(ms)', 'Max(ms)');
echo str_repeat('-', 70) . "\n";

foreach ($report as $label => $stats) {
    printf(
        "%-20s %6d %10.2f %10.2f %10.2f %10.2f\n",
        $label,
        $stats['count'],
        $stats['total_ms'],
        $stats['avg_ms'],
        $stats['min_ms'],
        $stats['max_ms'],
    );
}

echo "\n=== OK ===\n";
