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

echo "=== 05 — Semantic Search from Scratch ===\n\n";

$documents = [
    ['city' => 'Paris', 'country' => 'France', 'fact' => 'The Eiffel Tower is 330 meters tall.'],
    ['city' => 'Tokyo', 'country' => 'Japan', 'fact' => 'Shibuya Crossing is the busiest pedestrian crossing.'],
    ['city' => 'New York', 'country' => 'USA', 'fact' => 'The Statue of Liberty was a gift from France.'],
    ['city' => 'London', 'country' => 'UK', 'fact' => 'Big Ben is the nickname for the Great Bell of the clock.'],
    ['city' => 'Rome', 'country' => 'Italy', 'fact' => 'The Colosseum could hold 50,000 spectators.'],
    ['city' => 'Sydney', 'country' => 'Australia', 'fact' => 'The Opera House has over 1,000 rooms.'],
    ['city' => 'Cairo', 'country' => 'Egypt', 'fact' => 'The Great Pyramid is the oldest of the Seven Wonders.'],
    ['city' => 'Moscow', 'country' => 'Russia', 'fact' => 'Red Square separates the Kremlin from a merchant quarter.'],
];

$docTexts = array_map(fn(array $d): string => $d['city'] . ': ' . $d['fact'], $documents);

echo 'Indexing ' . count($documents) . " documents...\n";
$docVectors = array_map(fn(string $t): array => $embedder->embed($t), $docTexts);
echo "\n";

$queries = [
    'famous landmarks in Europe',
    'busy places with many people',
    'ancient historical buildings',
];

foreach ($queries as $query) {
    echo "Query: '$query'\n";
    $queryVec = $embedder->embed($query);

    $scores = [];

    foreach ($docVectors as $i => $docVec) {
        $scores[$i] = $embedder->cosineSimilarity($queryVec, $docVec);
    }
    arsort($scores);

    foreach (array_slice($scores, 0, 3, true) as $idx => $score) {
        printf("  %.4f  %s\n", $score, $docTexts[$idx]);
    }
    echo "\n";
}

echo "=== OK ===\n";
