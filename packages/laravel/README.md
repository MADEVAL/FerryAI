# ferry-ai/inference-laravel

Laravel integration for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only runtime for
PHP 8.3+. A thin adapter that wires FerryAI into the Laravel container.

## Installation

```bash
composer require ferry-ai/inference-laravel
```

## What's inside

- A service provider that builds FerryAI configuration via `FrameworkConfig` and calls `AI::config()`.
- A facade for accessing the FerryAI API from Laravel code.

Configuration is published to the app config directory; set model paths and backend options there.

## Requirements

- PHP >= 8.3
- `ferry-ai/inference-ai`
- Suggested: `laravel/framework` / `illuminate/support`

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/laravel.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/laravel.md).
