#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\AI;
use FerryAI\Laravel\AIServiceProvider;
use FerryAI\Laravel\Facades\AI as AIFacade;

echo "=== 19 — Laravel Integration ===\n\n";

echo "--- Service Provider ---\n\n";

AI::reset();

putenv('FERRY_AI_BACKEND=onnx');
putenv('FERRY_AI_DEVICE=cpu');
putenv('FERRY_AI_MAX_TOKENS=4096');
putenv('FERRY_AI_TEMPERATURE=0.8');

$provider = new AIServiceProvider();
$config = $provider->getConfig();

printf("config[backend]:       %s\n", $config['backend']);
printf("config[device]:        %s\n", $config['device']);
printf("config[max_tokens]:    %d\n", $config['max_tokens']);
printf("config[temperature]:   %.1f\n", $config['temperature']);
printf("config[model_cache]:   %s\n", $config['model_cache']);
printf("config[verify]:        %s\n\n", $config['verify_signatures'] ? 'true' : 'false');

$provider->register();
printf("AI::activeBackend():   %s\n", AI::activeBackend()->value);
printf("AI::activeDevice():    %s\n\n", AI::activeDevice()->value);

$provider->boot();

putenv('FERRY_AI_BACKEND');
putenv('FERRY_AI_DEVICE');
putenv('FERRY_AI_MAX_TOKENS');
putenv('FERRY_AI_TEMPERATURE');

echo "--- Facade ---\n\n";

AI::reset();
AI::config(['backend' => 'onnx']);

$backend = AIFacade::activeBackend();
printf("Facade::activeBackend(): %s\n\n", $backend->value);

echo "--- Config File ---\n\n";

echo "Typical Laravel config/ferry-ai.php:\n";
echo "```php\n";
echo "return [\n";
echo "    'backend' => env('FERRY_AI_BACKEND', 'auto'),\n";
echo "    'device'  => env('FERRY_AI_DEVICE', 'auto'),\n";
echo "    'model_cache' => storage_path('models'),\n";
echo "    'max_tokens' => 2048,\n";
echo "    'temperature' => 0.7,\n";
echo "    'backends' => [\n";
echo "        'llama' => ['model_path' => env('FERRY_AI_LLAMA_MODEL_PATH')],\n";
echo "    ],\n";
echo "];\n";
echo "```\n\n";

echo "--- Artisan Commands ---\n\n";

echo "Available artisan commands:\n";
echo "  ferry-ai:models:list        List cached models\n";
echo "  ferry-ai:models:download    Download a model from HuggingFace\n";
echo "  ferry-ai:models:prune       Clear model cache\n";
echo "  ferry-ai:tokenize {text}    Test tokenizer\n";
echo "  ferry-ai:chat {message}     Test chat\n\n";

echo "=== OK ===\n";
