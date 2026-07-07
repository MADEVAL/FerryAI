#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\Core\PlatformDetector;
use FerryAI\Profiler;

echo "=== Chat Benchmark ===\n\n";

$llamaDir = getenv('FERRY_AI_LLAMA_DIR') ?: dirname(__DIR__) . '/models';
$wrapExt = PlatformDetector::libExtension();
$wrapper = $llamaDir . DIRECTORY_SEPARATOR . 'ferry_llama.' . $wrapExt;
$modelPath = getenv('FERRY_AI_LLAMA_MODEL') ?: $llamaDir . DIRECTORY_SEPARATOR . 'qwen-0.5b.Q4_K_M.gguf';

if (!file_exists($wrapper) || !file_exists($modelPath)) {
    echo "SKIP: need ferry_llama.{$wrapExt} + a .gguf model in {$llamaDir}.\n";
    echo "  Build the wrapper: native/llama-wrapper/build.ps1 (Windows) / build.sh (Linux/macOS).\n";
    echo "  Override paths with FERRY_AI_LLAMA_DIR / FERRY_AI_LLAMA_MODEL.\n";
    exit(0);
}

putenv('FERRY_AI_LLAMA_WRAPPER=' . $wrapper);

AI::config([
    'backend' => 'llama',
    'device' => getenv('FERRY_AI_LLAMA_DEVICE') ?: 'cpu',
    'backends' => [
        'llama' => ['model_path' => $modelPath],
    ],
]);

$profiler = new Profiler();

$prompt = [['role' => 'user', 'content' => 'Write one sentence about the ocean.']];
$options = ['temperature' => 0.0, 'max_tokens' => 64];

$warmup = 1;
$runs = 5;

echo "Model:  {$modelPath}\n";
echo "Warmup: {$warmup} iteration(s)...\n\n";

for ($i = 0; $i < $warmup; $i++) {
    AI::chat($prompt, $options);
}

echo "--- Complete generation ({$runs} runs) ---\n\n";

$totalTokens = 0;
$totalMs = 0.0;

for ($i = 0; $i < $runs; $i++) {
    $profiler->start('chat.complete');
    $result = AI::chat($prompt, $options);
    $profiler->end('chat.complete');
    $totalTokens += $result->tokensGenerated;
    $totalMs += $result->durationMs;
}
$tokPerSec = $totalMs > 0.0 ? $totalTokens / ($totalMs / 1000.0) : 0.0;

echo "--- Streaming (first-token latency) ---\n\n";

$profiler->start('chat.stream');
$streamTokens = 0;
$firstTokenMs = null;
$streamStart = microtime(true) * 1000.0;

foreach (AI::stream($prompt, $options) as $piece) {
    if ($firstTokenMs === null) {
        $firstTokenMs = microtime(true) * 1000.0 - $streamStart;
    }
    ++$streamTokens;
}
$profiler->end('chat.stream');

echo "--- Results ---\n\n";

$report = $profiler->report();

printf("%-16s %6s %12s %12s\n", 'Operation', 'Runs', 'Total(ms)', 'Avg(ms)');
echo str_repeat('-', 50) . "\n";

foreach ($report as $label => $stats) {
    printf("%-16s %6d %12.2f %12.2f\n", $label, $stats['count'], $stats['total_ms'], $stats['avg_ms']);
}

printf("\nComplete: %d tokens over %d runs — %.1f tok/s\n", $totalTokens, $runs, $tokPerSec);
printf("Stream:   %d tokens, first-token latency %.1f ms\n", $streamTokens, $firstTokenMs ?? 0.0);

echo "\nDone.\n";
