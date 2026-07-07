#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\StreamResponse;

echo "=== 26 — Facade config-wiring (embed / similarity / stream response) ===\n\n";

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: dirname(__DIR__) . '/models/all-MiniLM-L6-v2-onnx';

echo "--- AI::embed() driven entirely by config ---\n\n";

if (!file_exists($modelDir . '/model.onnx') || !(new FerryAI\OnnxBackend\OnnxBackend())->isAvailable()) {
    echo "SKIP: ONNX Runtime or model dir unavailable ({$modelDir}).\n";
    echo "  Set FERRY_AI_MODEL_DIR to a dir with model.onnx + tokenizer.json.\n\n";
} else {
    AI::reset();
    AI::config([
        'backend' => 'onnx',
        'backends' => ['embedding' => ['model_path' => $modelDir]],
        'embedding' => ['pooling' => 'mean', 'normalize' => true],
    ]);

    $vec = AI::embed('Hello world');
    printf("AI::embed('Hello world') -> dim %d, model %s\n", $vec->dimension, basename($vec->modelName));
    printf("AI::similarity(cat,kitten):   %.3f\n", AI::similarity('cat', 'kitten'));
    printf("AI::similarity(cat,airplane): %.3f\n", AI::similarity('cat', 'airplane'));

    $batch = AI::embed(['red', 'green', 'blue']);
    printf("AI::embed(batch of 3) -> %d results\n\n", count($batch));

    AI::reset();
}

echo "--- StreamResponse::create() -> PSR-7 response ---\n\n";

$hasFactory = class_exists('Nyholm\Psr7\Factory\Psr17Factory') || class_exists('GuzzleHttp\Psr7\HttpFactory');

if (!$hasFactory) {
    echo "SKIP: install nyholm/psr7 or guzzlehttp/psr7 for PSR-7 streaming responses.\n";
} else {
    $response = StreamResponse::create(['Hello', ', ', 'world', '!']);
    printf("status:       %d\n", $response->getStatusCode());
    printf("content-type: %s\n", $response->getHeaderLine('Content-Type'));
    printf("body (SSE):   %s\n", str_replace("\n", '\\n', (string) $response->getBody()));
}

echo "\nRaw formats without any PSR-7 dependency:\n";
$raw = new StreamResponse(['tok1', 'tok2']);
printf("  toNdjson: %s\n", str_replace("\n", '\\n', $raw->toNdjson()));

echo "\n=== OK ===\n";
