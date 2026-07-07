#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\Core\PlatformDetector;
use FerryAI\LlamaBackend\ChatFormatter;

echo "=== 03 — LLM Chat ===\n\n";

$llamaDir = getenv('FERRY_AI_LLAMA_DIR') ?: (PHP_OS_FAMILY === 'Windows' ? 'D:\FerryAI' : '/opt/llama');
$wrapExt  = PlatformDetector::libExtension();
$wrapper  = $llamaDir . DIRECTORY_SEPARATOR . 'ferry_llama.' . $wrapExt;
$llamaPath = getenv('FERRY_AI_LLAMA_MODEL') ?: $llamaDir . DIRECTORY_SEPARATOR . 'qwen-0.5b.Q4_K_M.gguf';

if (!file_exists($wrapper) || !file_exists($llamaPath)) {
    echo "SKIP: need ferry_llama.{$wrapExt} + a .gguf model in {$llamaDir}.\n";
    echo "  Build the wrapper: native/llama-wrapper/build.ps1 (Windows) / build.sh (Linux/macOS).\n";
    echo "  Override paths with FERRY_AI_LLAMA_DIR / FERRY_AI_LLAMA_MODEL.\n";
    exit(0);
}

putenv('FERRY_AI_LLAMA_WRAPPER=' . $wrapper);
// FerryLlama sets the correct lib path per OS (PATH / LD_LIBRARY_PATH / DYLD_LIBRARY_PATH).

AI::config([
    'backend' => 'llama',
    'device' => getenv('FERRY_AI_LLAMA_DEVICE') ?: 'cpu',
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

echo "--- Sampler options ---\n\n";

$topK = AI::chat(
    [['role' => 'user', 'content' => 'Capital of France in one word?']],
    ['sampler' => 'top_k', 'temperature' => 0.7, 'max_tokens' => 4],
);
printf("top_k:   %s\n", trim($topK->text));

$grammar = AI::chat(
    [['role' => 'user', 'content' => 'Is the sky blue?']],
    ['grammar' => 'root ::= "yes" | "no"', 'max_tokens' => 6],
);
printf("grammar: %s\n\n", trim($grammar->text));

echo "=== OK ===\n";
