#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\Core\PlatformDetector;
use FerryAI\Core\RetryHandler;
use FerryAI\NativeBinaryManager;

echo "=== 16 — RetryHandler, PlatformDetector, NativeBinaryManager ===\n\n";

echo "--- PlatformDetector ---\n\n";

printf("os:           %s\n", PlatformDetector::os());
printf("arch:         %s\n", PlatformDetector::arch());
printf("libExtension: %s\n", PlatformDetector::libExtension());
printf("platformKey:  %s\n\n", PlatformDetector::platformKey());

echo "--- RetryHandler ---\n\n";

$handler = new RetryHandler();

$calls = 0;
$result = $handler->retry(function () use (&$calls): string {
    $calls++;
    if ($calls < 2) {
        throw new \RuntimeException("attempt $calls failed");
    }
    return 'success on attempt ' . $calls;
}, maxAttempts: 3, delayMs: 10, backoff: 'linear');

printf("retry(linear):  %s (after %d calls)\n", $result, $calls);

$calls = 0;
try {
    $handler->retry(function () use (&$calls): never {
        $calls++;
        throw new \RuntimeException("always fails");
    }, maxAttempts: 3, delayMs: 10);
} catch (\RuntimeException $e) {
    printf("retry(exhaust): %s (after %d calls)\n\n", $e->getMessage(), $calls);
}

echo "--- shouldRetry ---\n\n";

printf("RuntimeException:            %s\n", RetryHandler::shouldRetry(new \RuntimeException()) ? 'retry' : 'skip');
printf("ModelLoadException:          %s\n", RetryHandler::shouldRetry(
    new \FerryAI\Core\Exception\ModelLoadException('/p', 'bad'))
    ? 'retry' : 'skip');
printf("ShapeMismatchException:      %s\n", RetryHandler::shouldRetry(
    new \FerryAI\Core\Exception\ShapeMismatchException(
        new \FerryAI\Core\ValueObjects\Shape([3]),
        new \FerryAI\Core\ValueObjects\Shape([4])))
    ? 'retry' : 'skip');
printf("ModelNotFoundException:      %s\n\n", RetryHandler::shouldRetry(
    new \FerryAI\Core\Exception\ModelNotFoundException('/x'))
    ? 'retry' : 'skip');

echo "--- NativeBinaryManager ---\n\n";

$manager = new NativeBinaryManager();

$resolved = $manager->resolve('nonexistent_lib_xyz');
printf("resolve(unknown): %s\n", $resolved ?? 'null');

printf("verify(missing): %s\n\n", $manager->verify('/nonexistent', 'abc') ? 'PASS' : 'FAIL');

echo "=== OK ===\n";
