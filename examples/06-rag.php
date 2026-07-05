#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\OnnxBackend\OnnxBackend;
use FerryAI\Embedding\Embedder;
use FerryAI\Tokenizer\TokenizerFactory;
use FerryAI\Vector\CollectionManager;
use FerryAI\Vector\SQLiteStore;

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

echo "=== 06 — RAG (Retrieval-Augmented Generation) ===\n\n";

$store = new SQLiteStore(':memory:');
$manager = new CollectionManager($store);
$collection = $manager->create('knowledge_base', 384);

$chunks = [
    ['id' => 'chunk-1', 'text' => 'FerryAI is a PHP library for AI inference using ONNX Runtime, llama.cpp, and RubixML.', 'source' => 'docs'],
    ['id' => 'chunk-2', 'text' => 'FerryAI uses PHP FFI to bridge to native C libraries. No Python is required.', 'source' => 'docs'],
    ['id' => 'chunk-3', 'text' => 'Vector search uses cosine similarity with SQLite storage and brute-force fallback.', 'source' => 'docs'],
    ['id' => 'chunk-4', 'text' => 'The embedding model all-MiniLM-L6-v2 produces 384-dimensional vectors.', 'source' => 'docs'],
    ['id' => 'chunk-5', 'text' => 'Paris is the capital of France, known for the Eiffel Tower and Louvre Museum.', 'source' => 'wiki'],
    ['id' => 'chunk-6', 'text' => 'Tokyo is Japan\'s capital and the most populous metropolitan area in the world.', 'source' => 'wiki'],
    ['id' => 'chunk-7', 'text' => 'PHP 8.5 introduced the pipe operator and clone-with syntax for immutable objects.', 'source' => 'php'],
];

echo "Step 1: Embed and store " . count($chunks) . " chunks...\n";
foreach ($chunks as $chunk) {
    $vec = $embedder->embed($chunk['text']);
    $collection->add($chunk['id'], $vec, ['source' => $chunk['source'], 'text' => $chunk['text']]);
}
printf("Stored: %d vectors\n\n", $collection->count());

echo "Step 2: Search with metadata filter...\n\n";

$queries = [
    'How does FerryAI connect to native libraries?' => null,
    'Tell me about European capitals' => ['source' => ['eq' => 'wiki']],
];

foreach ($queries as $query => $filter) {
    echo "Query: '$query'\n";
    if ($filter) {
        echo "Filter: " . json_encode($filter) . "\n";
    }

    $qVec = $embedder->embed($query);
    $results = $collection->search($qVec, 3, $filter);

    foreach ($results as $r) {
        printf("  %.4f  [%s] %s\n", $r['distance'], $r['metadata']['source'] ?? '?', $r['metadata']['text'] ?? '');
    }
    echo "\n";
}

echo "=== OK ===\n";
