#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

echo "=== Chat Benchmark ===\n\n";
echo "Requires llama.cpp backend with validated FFI binding and a GGUF model.\n";
echo "Set FERRY_AI_LLAMA_MODEL to a GGUF file path.\n\n";

$modelPath = getenv('FERRY_AI_LLAMA_MODEL');
if (!$modelPath || !file_exists($modelPath)) {
    echo "SKIP: no GGUF model configured\n";
    exit(0);
}

echo "Ready for benchmarking with model: $modelPath\n";
