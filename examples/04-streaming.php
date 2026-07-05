#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\StreamResponse;

echo "=== 04 — Streaming ===\n\n";

$llamaPath = getenv('FERRY_AI_LLAMA_MODEL');
if (!$llamaPath || !file_exists($llamaPath)) {
    echo "SKIP: set FERRY_AI_LLAMA_MODEL to a GGUF file path (requires validated llama.cpp FFI binding)\n";
    exit(0);
}

AI::config([
    'backend' => 'llama',
    'device' => 'cpu',
    'backends' => ['llama' => ['model_path' => $llamaPath]],
]);

echo "--- Token-by-token Streaming ---\n\n";

$messages = [
    ['role' => 'user', 'content' => 'Count from 1 to 5.'],
];

echo "stream: ";
foreach (AI::stream($messages) as $token) {
    echo $token;
}
echo "\n\n";

echo "--- Server-Sent Events ---\n\n";

$sse = (new StreamResponse(AI::stream($messages)))->toSse();
echo "SSE output (first 200 chars):\n";
echo substr($sse, 0, 200) . "...\n\n";

echo "--- NDJSON ---\n\n";

$ndjson = (new StreamResponse(AI::stream($messages)))->toNdjson();
echo "NDJSON output (first 200 chars):\n";
echo substr($ndjson, 0, 200) . "...\n\n";

echo "=== OK ===\n";
