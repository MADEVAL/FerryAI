#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\Embedding\Embedder;
use FerryAI\Tokenizer\TokenizerFactory;

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: 'D:\FerryAI\all-MiniLM-L6-v2-onnx';
$modelPath = $modelDir . '/model.onnx';
$tokenizerPath = $modelDir . '/tokenizer.json';

if (!file_exists($modelPath)) {
    echo "=== SKIP: model not found at $modelPath ===\n";
    echo "Set FERRY_AI_MODEL_DIR or download: sentence-transformers/all-MiniLM-L6-v2\n";
    exit(0);
}

$backend = new OnnxBackend();
$tokenizer = (new TokenizerFactory())->createFromFile($tokenizerPath);
$embedder = new Embedder($modelPath, $backend, $tokenizer, 'mean', normalize: true);

echo "=== 01 — Hello Embedding ===\n\n";

$vec = $embedder->embed('Hello world');
printf("Text:       'Hello world'\n");
printf("Dimension:  %d\n", count($vec));
printf("Model:      %s\n", $embedder->modelName());
printf("Vector[0]:  %.4f\n", $vec[0]);
printf("Vector[1]:  %.4f\n", $vec[1]);
printf("Vector[2]:  %.4f\n\n", $vec[2]);

echo "--- Batch Embedding ---\n\n";

$batch = $embedder->embedBatch(['The cat sat on the mat', 'Dogs are loyal companions', 'Birds fly in the sky']);
printf("Batch size: %d\n", count($batch));
foreach ($batch as $i => $v) {
    printf("[%d] dim=%d  [0]=%.4f\n", $i, count($v), $v[0]);
}

echo "\n--- Similarity ---\n\n";

$sim = $embedder->cosineSimilarity($embedder->embed('cat'), $embedder->embed('kitten'));
printf("cat vs kitten:     %.4f\n", $sim);

$sim = $embedder->cosineSimilarity($embedder->embed('cat'), $embedder->embed('dog'));
printf("cat vs dog:        %.4f\n", $sim);

$sim = $embedder->cosineSimilarity($embedder->embed('cat'), $embedder->embed('airplane'));
printf("cat vs airplane:   %.4f\n\n", $sim);

echo "--- L2 Normalization ---\n\n";

$raw = $embedder->embed('test');
$normalized = $embedder->normalize($raw);
$norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $normalized)));
printf("raw norm:          %.4f\n", sqrt(array_sum(array_map(fn($v) => $v * $v, $raw))));
printf("normalized norm:   %.4f\n\n", $norm);

echo "=== OK ===\n";
