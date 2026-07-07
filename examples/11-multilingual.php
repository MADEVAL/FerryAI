#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Embedding\Embedder;
use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\Tokenizer\TokenizerFactory;

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

echo "=== 11 — Multilingual Embeddings ===\n\n";

$phrases = [
    'en' => 'good morning',
    'ru' => 'доброе утро',
    'zh' => '早上好',
    'ar' => 'صباح الخير',
    'fr' => 'bonjour',
    'de' => 'guten morgen',
    'es' => 'buenos días',
];

echo 'Embedding in ' . count($phrases) . " languages...\n\n";
$vectors = [];

foreach ($phrases as $lang => $text) {
    $vectors[$lang] = $embedder->embed($text);
    printf("  %-4s %-20s  dim=%-4d\n", $lang, $text, count($vectors[$lang]));
}

echo "\n--- Cross-lingual Similarity Matrix ---\n\n";

$langs = array_keys($phrases);
printf('%-6s', '');

foreach ($langs as $l) {
    printf('%-8s', $l);
}
echo "\n";

foreach ($langs as $a) {
    printf('%-6s', $a);

    foreach ($langs as $b) {
        $sim = $embedder->cosineSimilarity($vectors[$a], $vectors[$b]);
        printf('%-8.4f', $sim);
    }
    echo "\n";
}

echo "\n=== OK ===\n";
