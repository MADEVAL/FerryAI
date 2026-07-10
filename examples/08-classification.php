#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\Core\ValueObjects\ClassificationResult;

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: dirname(__DIR__) . '/models/all-MiniLM-L6-v2-onnx';
$modelPath = $modelDir . '/model.onnx';

if (!file_exists($modelPath)) {
    echo "=== SKIP: model not found at $modelPath ===\n";
    exit(0);
}

AI::config([
    'backend' => 'onnx',
    'device' => 'cpu',
    'backends' => [
        'classify' => ['model_path' => $modelPath],
        'moderate' => ['model_path' => $modelPath],
        'predict' => ['model_path' => $modelPath],
    ],
]);

echo "=== 08 — Classification, Moderation, Prediction ===\n\n";

echo "--- Classification ---\n\n";

try {
    $result = AI::classify('This product exceeded my expectations');
    assert($result instanceof ClassificationResult);
    printf("classify(positive review): label=%s confidence=%.4f\n\n", $result->label, $result->confidence);
} catch (\Throwable $e) {
    printf("classify: SKIP (%s)\n\n", $e->getMessage());
}

echo "--- Moderation ---\n\n";

try {
    $categories = AI::moderate('This is a completely normal and safe message');
    printf("moderate(normal text): flagged=%s\n", $categories['flagged'] ? 'YES' : 'NO');
    printf("moderate: categories=%s\n\n", json_encode($categories['categories']));
} catch (\Throwable $e) {
    printf("moderate: SKIP (%s)\n\n", $e->getMessage());
}

echo "--- Tabular Prediction ---\n\n";

try {
    $prediction = AI::predict(['feature_a' => 0.5, 'feature_b' => 1.2, 'feature_c' => -0.3]);
    printf("predict: %s\n\n", json_encode($prediction));
} catch (\Throwable $e) {
    printf("predict: SKIP (%s)\n\n", $e->getMessage());
}

echo "=== OK ===\n";
