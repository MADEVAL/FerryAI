#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;

$modelDir = getenv('FERRY_AI_MODEL_DIR') ?: 'D:\FerryAI\all-MiniLM-L6-v2-onnx';
$tokenizerPath = $modelDir . '/tokenizer.json';

if (!file_exists($tokenizerPath)) {
    echo "=== SKIP: tokenizer.json not found at $tokenizerPath ===\n";
    exit(0);
}

AI::config(['backend' => 'onnx']);
$tokenizer = AI::tokenizer($tokenizerPath);

echo "=== 02 — Tokenizer ===\n\n";

echo "--- Encode / Decode ---\n\n";

$ids = $tokenizer->encode('Hello world', addSpecialTokens: true);
printf("encode('Hello world'):  [%s] (len=%d)\n", implode(', ', $ids), count($ids));

$text = $tokenizer->decode($ids);
printf("decode(ids):            '%s'\n\n", $text);

echo "--- Special Tokens ---\n\n";

printf("vocabSize:      %d\n", $tokenizer->vocabSize());
printf("type:           %s\n", $tokenizer->type()->value);

$special = $tokenizer->specialTokens();
printf("specialTokens:  %s\n\n", json_encode($special));

echo "--- Count & Chunk ---\n\n";

$longText = 'The quick brown fox jumps over the lazy dog. ' .
    'Pack my box with five dozen liquor jugs. ' .
    'How vexingly quick daft zebras jump.';

printf("countTokens(long):  %d\n", $tokenizer->countTokens($longText));
printf("countTokens('hi'):  %d\n\n", $tokenizer->countTokens('hi'));

$chunks = $tokenizer->chunk($longText, maxTokens: 10, overlap: 3);
printf("chunks (max=10, overlap=3):  %d chunks\n", count($chunks));

foreach ($chunks as $i => $chunk) {
    printf("  [%d] len=%d: '%s...'\n", $i, strlen($chunk), substr($chunk, 0, 40));
}

echo "\n--- Batch Encoding ---\n\n";

$batch = $tokenizer->encodeBatch(['Hello', 'World', 'Test'], padToMaxLength: true);
printf("input_ids shape:      [%d x %d]\n", count($batch['input_ids']), count($batch['input_ids'][0]));
printf("attention_mask shape: [%d x %d]\n\n", count($batch['attention_mask']), count($batch['attention_mask'][0]));

echo "=== OK ===\n";
