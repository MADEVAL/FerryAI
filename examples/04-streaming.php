#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\StreamResponse;

echo "=== 04 — Streaming ===\n\n";

$llamaDir = getenv('FERRY_AI_LLAMA_DIR') ?: 'D:\FerryAI';
$wrapper = $llamaDir . '\ferry_llama.dll';
$llamaPath = getenv('FERRY_AI_LLAMA_MODEL') ?: $llamaDir . '\qwen-0.5b.Q4_K_M.gguf';

if (!file_exists($wrapper) || !file_exists($llamaPath)) {
    echo "SKIP: need ferry_llama.dll + a .gguf model in {$llamaDir}.\n";
    echo "  Build the wrapper: native/llama-wrapper/build.ps1 (see README 'LLM on CPU & GPU').\n";
    exit(0);
}

putenv('FERRY_AI_LLAMA_WRAPPER=' . $wrapper);
putenv('PATH=' . $llamaDir . PATH_SEPARATOR . (getenv('PATH') ?: ''));

AI::config([
    'backend' => 'llama',
    'device' => getenv('FERRY_AI_LLAMA_DEVICE') ?: 'cpu',
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
