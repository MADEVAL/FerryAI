# ferry-ai/inference-symfony

Symfony integration for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only runtime for
PHP 8.5+. A thin bundle that wires FerryAI into the Symfony container.

## Installation

```bash
composer require ferry-ai/inference-symfony
```

## What's inside

- A Symfony bundle and DI extension that build FerryAI configuration via `FrameworkConfig` and call
  `AI::config()`.
- A configuration tree for model paths and backend options.

## Requirements

- PHP >= 8.5
- `ferry-ai/inference-ai`
- Suggested: `symfony/http-kernel`, `symfony/config`, `symfony/dependency-injection`

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/symfony.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/symfony.md).
