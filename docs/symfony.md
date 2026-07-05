# Symfony integration

`ferry-ai/inference-symfony` provides a bundle and a DI extension that configure FerryAI in a
Symfony app.

> Like the Laravel adapter, the Symfony classes are decoupled: `AIBundle` does **not** extend
> Symfony’s `Bundle`, `Configuration` does **not** implement `ConfigurationInterface`, and
> `FerryAIExtension` does **not** extend `Extension` — no hard Symfony dependency (AGENTS rule 7).
> Add `symfony/http-kernel`, `symfony/config`, `symfony/dependency-injection` yourself (`suggest`)
> to wire them natively. See `docs/DEBT_REPORT.md` §7.

## Bundle

`AIBundle::boot()` reads configuration and calls `AI::config()`. `FerryAIExtension` loads
`config/packages/ferry_ai.yaml`; `Configuration` describes the config tree.

```yaml
# config/packages/ferry_ai.yaml
ferry_ai:
    backend: onnx
    device: cpu
    backends:
        embedding:
            model_path: '%kernel.project_dir%/models/all-MiniLM-L6-v2-onnx'
        llama:
            model_path: '%kernel.project_dir%/models/model.gguf'
```

## Use in services

Once booted, use the facade anywhere:

```php
use FerryAI\AI;

$reply = AI::chat([['role' => 'user', 'content' => $prompt]]);
```

See [`examples/20-symfony.php`](../examples/20-symfony.php) and [api-reference](api-reference.md).

> Not yet tested inside a real Symfony application — see `docs/DEBT_REPORT.md` §7.
