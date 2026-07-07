#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Embedding\Embedder;
use FerryAI\Metrics;
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\Profiler;
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

$profiler = new Profiler();
Metrics::reset();

echo "=== 13 — Profiling & Metrics ===\n\n";

echo "--- Profiler ---\n\n";

$profiler->start('embed.total');

$iterations = 10;

for ($i = 0; $i < $iterations; $i++) {
    $profiler->start('embed.single');
    $embedder->embed("test text $i");
    $profiler->end('embed.single');
}

$profiler->end('embed.total');

$report = $profiler->report();
printf("Total iterations:  %d\n", $iterations);
printf("Total duration:    %.2f ms\n", $report['embed.total']['total_ms']);
printf("Avg per embed:     %.2f ms\n", $report['embed.single']['avg_ms']);
printf("Min:               %.2f ms\n", $report['embed.single']['min_ms']);
printf("Max:               %.2f ms\n\n", $report['embed.single']['max_ms']);

echo "--- Metrics ---\n\n";

Metrics::increment('requests_total', ['backend' => 'onnx']);
Metrics::increment('requests_total', ['backend' => 'onnx']);
Metrics::increment('requests_total', ['backend' => 'llama']);
Metrics::record('active_models', 3.0);

Metrics::timing('inference_ms', 12.5, ['model' => 'MiniLM']);
Metrics::timing('inference_ms', 15.3, ['model' => 'MiniLM']);
Metrics::timing('inference_ms', 45.0, ['model' => 'mpnet']);

$mReport = Metrics::report();

echo "counters:\n";

foreach ($mReport['counters'] as $key => $data) {
    printf("  %-45s = %.1f\n", $key, $data['value']);
}

echo "\ntimings:\n";

foreach ($mReport['timings'] as $key => $values) {
    $avg = array_sum($values) / count($values);
    printf("  %-30s n=%d avg=%.2f ms\n", $key, count($values), $avg);
}

echo "\n=== OK ===\n";
