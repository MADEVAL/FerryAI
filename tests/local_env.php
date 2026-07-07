<?php

declare(strict_types=1);

/*
 * Loads developer-local environment overrides for examples, benchmarks and integration
 * tests. Registered as a Composer "autoload-dev" files entry, so it runs for every entry
 * point that includes the autoloader (examples, benchmarks, bin, PHPUnit) without committing
 * any machine-specific paths to the repository.
 *
 * Copy `.ferry-ai.local.php.dist` to `.ferry-ai.local.php` (git-ignored) and set your paths.
 * When the local file is absent, the code falls back to the repo-relative `models/` directory
 * and skips gracefully if the assets are missing.
 */

$ferryAiLocalConfig = \dirname(__DIR__) . '/.ferry-ai.local.php';

if (\is_file($ferryAiLocalConfig)) {
    require $ferryAiLocalConfig;
}
