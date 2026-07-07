<?php

declare(strict_types=1);

/*
 * Standalone harness: real llama.cpp chat through the FerryAI facade + ferry_llama
 * wrapper, in its OWN process (loading the DLL runs ggml global ctors that conflict
 * with PHPUnit). Prints one JSON line for the test to assert.
 *
 * Args: [device] [maxTokens]   e.g.  php llama_chat_harness.php cpu 16
 */

require __DIR__ . '/../../../vendor/autoload.php';

use FerryAI\AI;

$device = $argv[1] ?? 'cpu';
$maxTokens = (int) ($argv[2] ?? 16);
$temperature = isset($argv[3]) ? (float) $argv[3] : 0.0;
$mode = $argv[4] ?? 'single';

$llamaDir = getenv('FERRY_AI_LLAMA_DIR') ?: (\PHP_OS_FAMILY === 'Windows' ? 'D:\\FerryAI' : '/opt/llama');
$ext = \PHP_OS_FAMILY === 'Windows' ? 'dll' : (\PHP_OS_FAMILY === 'Darwin' ? 'dylib' : 'so');
$wrapper = $llamaDir . \DIRECTORY_SEPARATOR . 'ferry_llama.' . $ext;
$model = getenv('FERRY_AI_LLAMA_MODEL') ?: $llamaDir . \DIRECTORY_SEPARATOR . 'qwen-0.5b.Q4_K_M.gguf';

if (!is_file($wrapper) || !is_file($model)) {
    echo json_encode(['skip' => "need $wrapper and $model"]);
    exit(0);
}

putenv('FERRY_AI_LLAMA_WRAPPER=' . $wrapper);

if (\PHP_OS_FAMILY === 'Windows') {
    putenv('PATH=' . $llamaDir . PATH_SEPARATOR . (getenv('PATH') ?: ''));
}

try {
    AI::config([
        'backend' => 'llama',
        'device' => $device,
        'backends' => ['llama' => ['model_path' => $model]],
    ]);

    $chat = static function () use ($maxTokens, $temperature): array {
        $t0 = microtime(true);
        $result = AI::chat(
            [['role' => 'user', 'content' => 'What is the capital of France? Answer in one word.']],
            ['max_tokens' => $maxTokens, 'temperature' => $temperature],
        );

        return ['text' => $result->text, 'ms' => (microtime(true) - $t0) * 1000, 'result' => $result];
    };

    if ($mode === 'grammar') {
        $r = AI::chat(
            [['role' => 'user', 'content' => 'Is the sky blue?']],
            ['max_tokens' => 8, 'grammar' => 'root ::= "yes" | "no"'],
        );

        echo json_encode(['device' => $device, 'text' => trim($r->text)]);

        return;
    }

    if ($mode === 'twice') {
        // Second call must reuse the pooled model (no reload) => noticeably faster.
        $a = $chat();
        $b = $chat();

        echo json_encode([
            'device' => $device,
            'text1' => $a['text'],
            'text2' => $b['text'],
            'ms1' => round($a['ms']),
            'ms2' => round($b['ms']),
        ]);

        return;
    }

    $first = $chat();
    $result = $first['result'];

    echo json_encode([
        'device' => $device,
        'text' => $result->text,
        'tokens_generated' => $result->tokensGenerated,
        'tokens_prompt' => $result->tokensPrompt,
        'ms' => round($first['ms']),
    ]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
