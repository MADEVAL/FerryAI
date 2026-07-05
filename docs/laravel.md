# Laravel integration

`ferry-ai/inference-laravel` provides a service provider and facade that wire FerryAI into a
Laravel app using environment-based config.

> The adapters are intentionally decoupled: `AIServiceProvider` does **not** extend
> `Illuminate\Support\ServiceProvider` and `Facades\AI` does **not** extend Laravel’s `Facade`
> base — FerryAI has no hard Laravel dependency (AGENTS rule 7). Add `laravel/framework` yourself
> (`suggest`) to register them the Laravel way. See `docs/DEBT_REPORT.md` §7.

## Register

In a real app, call the provider’s `register()`/`boot()` (from your own provider or bootstrap):

```php
$provider = new FerryAI\Laravel\AIServiceProvider($app);
$provider->register();   // builds config from env and calls AI::config()
$provider->boot();       // warms up models listed in FERRY_AI_WARMUP
```

## Environment config

| Env | Maps to |
|-----|---------|
| `FERRY_AI_BACKEND` | `backend` |
| `FERRY_AI_DEVICE` | `device` |
| `FERRY_AI_MODEL_CACHE` | `model_cache` |
| `FERRY_AI_MAX_TOKENS` / `FERRY_AI_TEMPERATURE` / `FERRY_AI_TOP_P` | sampling defaults |
| `FERRY_AI_ONNX_PROVIDERS` / `FERRY_AI_ONNX_OPTIMIZATION` | `backends.onnx.*` |
| `FERRY_AI_LLAMA_MODEL_PATH` / `FERRY_AI_LLAMA_N_CTX` / `FERRY_AI_LLAMA_GPU_LAYERS` | `backends.llama.*` |
| `FERRY_AI_VERIFY_SIGNATURES` | `verify_signatures` |
| `FERRY_AI_WARMUP` | comma-separated model ids to preload |
| `FERRY_AI_LOG_CHANNEL` | log channel |

## Use in controllers

```php
use FerryAI\Laravel\Facades\AI;

$vec = AI::embed($request->input('text'));
```

`Facades\AI` proxies to `FerryAI\AI`, so the full facade API is available. See
[`examples/19-laravel.php`](../examples/19-laravel.php) and [api-reference](api-reference.md).

> Not yet tested inside a real Laravel application — see `docs/DEBT_REPORT.md` §7.
