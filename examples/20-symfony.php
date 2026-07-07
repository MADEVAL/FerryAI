#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\Symfony\AIBundle;
use FerryAI\Symfony\DependencyInjection\Configuration;
use FerryAI\Symfony\DependencyInjection\FerryAIExtension;

echo "=== 20 — Symfony Integration ===\n\n";

echo "--- Bundle ---\n\n";

AI::reset();

$bundle = new AIBundle();
$bundle->boot();

printf("AI::activeBackend(): %s\n", AI::activeBackend()->value);
printf("AI::activeDevice():  %s\n\n", AI::activeDevice()->value);

echo "--- Configuration Tree ---\n\n";

$config = new Configuration();
$tree = $config->getConfigTree();

printf("ferry_ai.backend:         %s\n", $tree['ferry_ai']['backend']);
printf("ferry_ai.device:          %s\n", $tree['ferry_ai']['device']);
printf("ferry_ai.model_cache:     %s\n", $tree['ferry_ai']['model_cache']);
printf(
    "ferry_ai.backends.onnx.providers: %s\n\n",
    implode(', ', $tree['ferry_ai']['backends']['onnx']['providers']),
);

echo "--- DI Extension ---\n\n";

$extension = new FerryAIExtension();

$extension->load([
    ['backend' => 'llama', 'device' => 'cuda', 'max_tokens' => 8192],
]);

$loaded = $extension->getConfig();
printf("loaded[backend]:     %s\n", $loaded['backend']);
printf("loaded[device]:      %s\n", $loaded['device']);
printf("loaded[max_tokens]:  %d\n\n", $loaded['max_tokens']);

$extension->load([]);

$defaults = $extension->getConfig();
printf("defaults[backend]:   %s\n\n", $defaults['backend']);

echo "--- Symfony Config (config/packages/ferry_ai.yaml) ---\n\n";

echo "```yaml\n";
echo "ferry_ai:\n";
echo "    backend: '%env(FERRY_AI_BACKEND)%'\n";
echo "    device: '%env(FERRY_AI_DEVICE)%'\n";
echo "    model_cache: '%kernel.project_dir%/var/models'\n";
echo "    max_tokens: 2048\n";
echo "    temperature: 0.7\n";
echo "    backends:\n";
echo "        llama:\n";
echo "            model_path: '%env(FERRY_AI_LLAMA_MODEL_PATH)%'\n";
echo "```\n\n";

echo "=== OK ===\n";
