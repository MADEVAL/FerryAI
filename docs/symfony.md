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

`AIBundle::boot(array $configs = [])` merges the given config over the bundle defaults and
calls `AI::config()`. `FerryAIExtension::load()` performs the same wiring inside a Symfony
container. `Configuration::getConfigTree()` returns the **default** tree shown below; any
additional [`AIConfig`](configuration.md) key you supply (e.g. `embedding.*`, `vector.*`,
`model_pool.*`, `observability.*`) is passed straight through to `AI::config()`.

## Configuration

Bundle defaults (from `Configuration::getConfigTree()`):

```yaml
# config/packages/ferry_ai.yaml
ferry_ai:
    backend: auto
    device: auto
    model_cache: '%kernel.project_dir%/var/models'
    max_tokens: 2048
    temperature: 0.7
    top_p: 1.0
    verify_signatures: true
    backends:
        onnx:
            providers: ['CUDA', 'CPU']
            graph_optimization: ALL
        llama:
            model_path: ~
            n_ctx: 2048
            n_gpu_layers: 0
    warmup: []
    log_channel: stack
```

Additional keys accepted by `AI::config()` (not part of the default tree, but forwarded if
present) — see [configuration](configuration.md) for the full list:

```yaml
ferry_ai:
    backends:
        embedding: { model_path: '%kernel.project_dir%/models/all-MiniLM-L6-v2-onnx' }
        classify:  { model_path: ~ }
    embedding: { pooling: mean, normalize: true }
    vector:    { driver: sqlite, db_path: ':memory:', metric: cosine }
    observability: { metrics: false, profiling: false, logging: false }
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

## Accessing FerryAI

The adapter does not register container services; use the static `FerryAI\AI` facade directly
from any service or controller once the bundle has booted. To inject FerryAI behind your own
service, create thin wrappers in your app and call `AI::embed()` / `AI::vector()` inside them:

```php
// config/services.yaml
services:
    App\Service\RagService: ~   # calls FerryAI\AI internally
```

See [`examples/20-symfony.php`](../examples/20-symfony.php) and
[api-reference](api-reference.md).
