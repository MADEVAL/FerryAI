#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Pipeline\Pipeline;
use FerryAI\Pipeline\Stages\ChunkStage;
use FerryAI\Pipeline\Stages\FilterStage;
use FerryAI\Pipeline\Stages\NormalizeStage;
use FerryAI\Pipeline\Stages\TransformStage;
use FerryAI\AI;

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: 'D:\FerryAI\all-MiniLM-L6-v2-onnx';
$tokenizerPath = $modelDir . '/tokenizer.json';

if (!file_exists($tokenizerPath)) {
    echo "=== SKIP: tokenizer.json not found at $tokenizerPath ===\n";
    exit(0);
}

AI::config(['backend' => 'onnx']);
$tokenizer = AI::tokenizer($tokenizerPath);

echo "=== 07 — Pipeline ===\n\n";

echo "--- Transform + Filter ---\n\n";

$pipeline = new Pipeline();

$pipeline
    ->pipe(new TransformStage(strtoupper(...)))
    ->pipe(new TransformStage(fn(string $s): string => $s . '!'))
    ->pipe(new FilterStage(fn(string $s): bool => strlen($s) > 5));

$inputs = ['hi', 'hello', 'greetings', 'yo'];

echo "Input:  " . json_encode($inputs) . "\n";
$results = iterator_to_array($pipeline->run($inputs));
echo "Output: " . json_encode($results) . "\n";
printf("stages: %s\n", implode(' → ', array_map(fn($s) => $s->name(), $pipeline->stages())));
echo "\n";

echo "--- Normalize Stage ---\n\n";

$p2 = new Pipeline();
$p2->pipe(new NormalizeStage());

$vector = [3.0, 4.0, 0.0, 0.0];
$results = iterator_to_array($p2->run([$vector]));
$norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $results[0])));
printf("Input:  [%s]\n", implode(', ', $vector));
printf("Output: [%.4f, %.4f, ...]  norm=%.4f\n\n", $results[0][0], $results[0][1], $norm);

echo "--- Chunk Stage ---\n\n";

$p3 = new Pipeline();
$p3->pipe(new ChunkStage($tokenizer, maxTokens: 10, overlap: 3));

$longText = 'This is a test of the chunking stage for the pipeline system in FerryAI.';
$results = iterator_to_array($p3->run($longText));
printf("Text: '%s'\n", $longText);
printf("Chunks: %s\n\n", json_encode($results));

echo "--- PHP 8.5 Pipe Operator ---\n\n";

$p4 = new Pipeline();
$p4->pipe(new TransformStage(fn(string $x): string => "[$x]"));
$results = iterator_to_array($p4('hello'));
echo $results[0] . "\n\n";

echo "=== OK ===\n";
