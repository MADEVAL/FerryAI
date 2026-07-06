# Symfony integration

`ferry-ai/inference-symfony` provides a bundle and a DI extension that configure FerryAI
in a Symfony application.

> Like the Laravel adapter, the Symfony classes are decoupled: `AIBundle` does **not** extend
> Symfony's `Bundle`, `Configuration` does **not** implement `ConfigurationInterface`, and
> `FerryAIExtension` does **not** extend `Extension` — no hard Symfony dependency.
> Add `symfony/http-kernel`, `symfony/config`, `symfony/dependency-injection` yourself
> (`suggest`) to wire them natively.

## Package

```
packages/symfony/src/
├── AIBundle.php                          # Bootstraps FerryAI from config
└── DependencyInjection/
    ├── Configuration.php                 # Defines the config tree
    └── FerryAIExtension.php              # Loads config and sets up container
```

## Bundle

`AIBundle::boot()` reads the Symfony configuration tree and calls `AI::config()`.
`FerryAIExtension` loads `config/packages/ferry_ai.yaml`. `Configuration` describes
the full valid config tree.

## Configuration

```yaml
# config/packages/ferry_ai.yaml
ferry_ai:
    backend: onnx
    device: cpu
    model_cache: '%kernel.cache_dir%/ferry-ai-models'
    max_tokens: 2048
    temperature: 0.7
    verify_signatures: true
    backends:
        embedding:
            model_path: '%kernel.project_dir%/models/all-MiniLM-L6-v2-onnx'
            tokenizer_path: ~
        classify:
            model_path: ~
        moderate:
            model_path: ~
        predict:
            model_path: ~
        llama:
            model_path: '%kernel.project_dir%/models/model.gguf'
            n_ctx: 2048
            n_gpu_layers: 0
            lib_path: ~
    embedding:
        pooling: mean
        normalize: true
    vector:
        driver: sqlite
        db_path: ':memory:'
        metric: cosine
    model_pool:
        max_memory_bytes: ~
        shared_memory: false
    observability:
        metrics: false
        profiling: false
        logging: false
```

## Register the bundle

In `config/bundles.php` (or via manual registration):

```php
return [
    // ...
    FerryAI\Symfony\AIBundle::class => ['all' => true],
];
```

Or manually:

```php
$bundle = new FerryAI\Symfony\AIBundle();
$bundle->boot();   // reads config and calls AI::config()
```

## Use in services

Once booted, use the facade anywhere:

```php
<?php

namespace App\Controller;

use FerryAI\AI;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class InferenceController
{
    public function embed(Request $request): JsonResponse
    {
        $text = $request->get('text');
        $vec  = AI::embed($text);

        return new JsonResponse([
            'dimension' => $vec->dimension,
            'model'     => $vec->modelName,
        ]);
    }

    public function chat(Request $request): JsonResponse
    {
        $prompt = $request->get('prompt');
        $reply  = AI::chat([['role' => 'user', 'content' => $prompt]]);

        return new JsonResponse(['reply' => $reply->text]);
    }
}
```

## Dependency injection (optional)

The extension can register FerryAI services in the container for DI-based access:

```yaml
# config/services.yaml
services:
    App\Service\RagService:
        arguments:
            $embedder: '@ferry_ai.embedder'
            $vectorStore: '@ferry_ai.vector_store'
```

See [`examples/20-symfony.php`](../examples/20-symfony.php) and
[api-reference](api-reference.md).
