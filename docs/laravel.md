# Laravel integration

`ferry-ai/inference-laravel` provides a service provider and facade that wire FerryAI into
a Laravel application using environment-based configuration.

> The adapters are intentionally decoupled: `AIServiceProvider` does **not** extend
> `Illuminate\Support\ServiceProvider` and `Facades\AI` does **not** extend Laravel's
> `Facade` base class — FerryAI has no hard Laravel dependency. Add
> `laravel/framework` yourself (`suggest`) to register them the Laravel way.

## Package

```
packages/laravel/src/
├── AIServiceProvider.php     # Builds config from env, calls AI::config(), warms up models
└── Facades/AI.php            # Proxies to FerryAI\AI
```

## Register

In a real app, call the provider's `register()` and `boot()` from your own provider or
bootstrap:

```php
$app = app();   // Illuminate\Foundation\Application

$provider = new FerryAI\Laravel\AIServiceProvider($app);
$provider->register();   // reads env, calls AI::config()
$provider->boot();       // warms up models from FERRY_AI_WARMUP
```

Or register it in `config/app.php`:

```php
'providers' => [
    // ...
    FerryAI\Laravel\AIServiceProvider::class,
],
```

## Environment configuration

The provider reads these environment variables and maps them to FerryAI config keys:

| Env | Maps to |
|-----|---------|
| `FERRY_AI_BACKEND` | `backend` |
| `FERRY_AI_DEVICE` | `device` |
| `FERRY_AI_MODEL_CACHE` | `model_cache` |
| `FERRY_AI_MAX_TOKENS` | `max_tokens` |
| `FERRY_AI_TEMPERATURE` | `temperature` |
| `FERRY_AI_TOP_P` | `top_p` |
| `FERRY_AI_ONNX_PROVIDERS` | `backends.onnx.providers` |
| `FERRY_AI_ONNX_OPTIMIZATION` | `backends.onnx.optimization` |
| `FERRY_AI_ONNX_MODEL_PATH` | `backends.embedding.model_path` |
| `FERRY_AI_LLAMA_MODEL_PATH` | `backends.llama.model_path` |
| `FERRY_AI_LLAMA_N_CTX` | `backends.llama.n_ctx` |
| `FERRY_AI_LLAMA_N_GPU_LAYERS` | `backends.llama.n_gpu_layers` |
| `FERRY_AI_LLAMA_LIB` | `backends.llama.lib_path` |
| `FERRY_AI_VERIFY_SIGNATURES` | `verify_signatures` |
| `FERRY_AI_WARMUP` | Comma-separated model IDs to preload on boot |
| `FERRY_AI_LOG_CHANNEL` | Log channel for FerryAI events |

## Use in controllers

```php
<?php

namespace App\Http\Controllers;

use FerryAI\Laravel\Facades\AI;

class EmbedController
{
    public function embed(Request $request): array
    {
        $text = $request->input('text');
        $vec  = AI::embed($text);

        return ['dimension' => $vec->dimension, 'vector' => $vec->vector];
    }
}

class ChatController
{
    public function chat(Request $request)
    {
        $messages = [['role' => 'user', 'content' => $request->input('prompt')]];
        $reply = AI::chat($messages);

        return response()->json(['reply' => $reply->text]);
    }
}
```

`Facades\AI` proxies every call to `FerryAI\AI`, so the full facade API is available.
See [`examples/19-laravel.php`](../examples/19-laravel.php) and
[api-reference](api-reference.md).

## Laravel config file (optional)

You can also publish a config file:

```php
// config/ferry-ai.php
return [
    'backend' => env('FERRY_AI_BACKEND', 'onnx'),
    'device'  => env('FERRY_AI_DEVICE', 'cpu'),
    'backends' => [
        'embedding' => [
            'model_path' => env('FERRY_AI_EMBEDDING_MODEL_PATH', storage_path('models/all-MiniLM-L6-v2-onnx')),
        ],
        'llama' => [
            'model_path' => env('FERRY_AI_LLAMA_MODEL_PATH'),
        ],
    ],
    'model_cache' => env('FERRY_AI_MODEL_CACHE', storage_path('ferry-ai-models')),
];
```
