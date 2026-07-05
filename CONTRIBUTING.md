# Contributing

## Development Setup

```bash
git clone https://github.com/MADEVAL/FerryAI
cd FerryAI
composer install
```

## Pre-commit Gate

```bash
composer check    # cs-fix + PHPStan lvl8 + Psalm lvl3 + unit tests
```

## Testing

- **Unit tests**: `composer test` — pure PHP, no native libs needed
- **Integration tests**: `composer test-integration` — requires ONNX Runtime / llama.cpp
- **TDD**: write failing test → minimal code → `composer check` green

## Code Style

PER-CS 2.0. Run `composer cs-fix` before committing.

## Commit Convention

`type(scope): description`

Types: feat, fix, docs, style, refactor, perf, test, chore, ci, build, revert
Scope: package name (core, onnx-backend, ai, ...)

## Documents

See `docs/README.md` for full navigation.
