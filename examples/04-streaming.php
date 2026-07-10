#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\Core\PlatformDetector;
use FerryAI\StreamResponse;

echo "=== 04 — Streaming ===\n\n";

$llamaDir = getenv('FERRY_AI_LLAMA_DIR') ?: dirname(__DIR__) . '/models';
$wrapExt  = PlatformDetector::libExtension();
$wrapper  = $llamaDir . DIRECTORY_SEPARATOR . 'ferry_llama.' . $wrapExt;
$llamaPath = getenv('FERRY_AI_LLAMA_MODEL') ?: $llamaDir . DIRECTORY_SEPARATOR . 'qwen-0.5b.Q4_K_M.gguf';

if (!file_exists($wrapper) || !file_exists($llamaPath)) {
    echo "SKIP: need ferry_llama.{$wrapExt} + a .gguf model in {$llamaDir}.\n";
    echo "  Build the wrapper: native/llama-wrapper/build.ps1 (Windows) / build.sh (Linux/macOS).\n";
    exit(0);
}

putenv('FERRY_AI_LLAMA_WRAPPER=' . $wrapper);
// FerryLlama sets the correct lib path per OS (PATH / LD_LIBRARY_PATH / DYLD_LIBRARY_PATH).

AI::config([
    'backend' => 'llama',
    'device' => getenv('FERRY_AI_LLAMA_DEVICE') ?: 'cpu',
    'backends' => ['llama' => ['model_path' => $llamaPath]],
]);

echo "--- Token-by-token Streaming ---\n\n";

$messages = [
    ['role' => 'user', 'content' => 'Count from 1 to 5.'],
];

echo 'stream: ';

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
