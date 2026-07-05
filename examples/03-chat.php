#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\LlamaBackend\ChatFormatter;

echo "=== 03 — LLM Chat ===\n\n";

$llamaPath = getenv('FERRY_AI_LLAMA_MODEL');
if (!$llamaPath || !file_exists($llamaPath)) {
    echo "SKIP: set FERRY_AI_LLAMA_MODEL to a GGUF file path (requires validated llama.cpp FFI binding)\n";
    exit(0);
}

AI::config([
    'backend' => 'llama',
    'device' => 'cpu',
    'backends' => [
        'llama' => ['model_path' => $llamaPath],
    ],
]);

echo "--- ChatFormatter Templates ---\n\n";

$formats = ['llama3', 'chatml', 'mistral', 'gemma', 'phi'];
foreach ($formats as $fmt) {
    $formatter = new ChatFormatter($fmt);
    printf("  %-8s: %s\n", $fmt, $formatter->format([
        ['role' => 'system', 'content' => 'You are helpful.'],
        ['role' => 'user', 'content' => 'What is PHP?'],
    ]));
}

echo "\n--- Single Turn ---\n\n";

$result = AI::chat([
    ['role' => 'system', 'content' => 'Answer in one sentence.'],
    ['role' => 'user', 'content' => 'What is the capital of France?'],
]);

printf("response:        %s\n", $result->text);
printf("tokensGenerated: %d\n", $result->tokensGenerated);
printf("tokensPrompt:    %d\n", $result->tokensPrompt);
printf("durationMs:      %.0f ms\n\n", $result->durationMs);

echo "--- Multi Turn ---\n\n";

$result = AI::chat([
    ['role' => 'user', 'content' => 'My name is Alex.'],
    ['role' => 'assistant', 'content' => 'Hello Alex! How can I help?'],
    ['role' => 'user', 'content' => 'What is my name?'],
]);

printf("response: %s\n\n", $result->text);

echo "=== OK ===\n";
