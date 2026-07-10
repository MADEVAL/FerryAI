<?php

declare(strict_types=1);

/**
 * Test bootstrap for integration tests.
 *
 * Prepares the environment for tests that require native libraries.
 */

\putenv('FERRY_AI_TESTING=1');

if (\getenv('FERRY_AI_SKIP_NATIVE') !== '1' && \getenv('FERRY_AI_INTEGRATION') === '1') {
    \putenv('FERRY_AI_SKIP_NATIVE=0');
}
