#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use FerryAI\StreamResponse;

echo "=== 18 — HTTP Stream Response ===\n\n";

$tokens = ['Hello', ' ', 'World', '!', ' ', 'This', ' ', 'is', ' ', 'streaming', '.'];

echo "--- Server-Sent Events ---\n\n";

$response = new StreamResponse($tokens);
$sse = $response->toSse();

echo $sse;

echo "--- NDJSON ---\n\n";

$ndjson = $response->toNdjson();

echo $ndjson;

echo "--- PSR-7 Response ---\n\n";

echo "StreamResponse::create() produces a PSR-7 ResponseInterface\n";
echo "with Content-Type: text/event-stream for SSE streaming.\n";
echo "Requires a PSR-7 implementation (nyholm/psr7, guzzlehttp/psr7).\n\n";

echo "=== OK ===\n";
