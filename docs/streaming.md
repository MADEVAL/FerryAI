# Streaming

Generate LLM output token-by-token and expose it as HTTP-friendly streams.

## Token stream

`AI::stream()` returns a `Generator<int, string>` of decoded pieces:

```php
foreach (AI::stream([['role' => 'user', 'content' => 'Count to 5']]) as $piece) {
    echo $piece;          // 1 2 3 4 5
    @ob_flush(); @flush();
}
```

Requires the llama backend — see [backends/llama](backends/llama.md). Options match
`AI::chat()` (`temperature`, `sampler`, `grammar`, `max_tokens`, …).

## Raw SSE / NDJSON

`FerryAI\StreamResponse` formats a token iterable without any HTTP dependency:

```php
$sr = new FerryAI\StreamResponse(AI::stream($messages));
echo $sr->toSse();        // "data: 1\n\ndata: 2\n\n…"
echo $sr->toNdjson();     // {"token":"1"}\n{"token":"2"}\n…
```

See [`examples/04-streaming.php`](../examples/04-streaming.php),
[`examples/18-stream-response.php`](../examples/18-stream-response.php).

## PSR-7 response

`AI::streamResponse()` (or `StreamResponse::create()`) returns a PSR-7
`ResponseInterface` with `Content-Type: text/event-stream`, when a PSR-17 factory is installed
(`nyholm/psr7` or `guzzlehttp/psr7`):

```php
$response = AI::streamResponse([['role' => 'user', 'content' => 'Hi']]);
$response->getStatusCode();                 // 200
(string) $response->getBody();              // SSE body
```

Without a PSR-17 factory it throws a clear error — use `toSse()`/`toNdjson()` instead.

## Real-time flushing

For live streaming to a browser, disable output buffering and flush after each piece; behind
Nginx/FPM also disable proxy buffering (`X-Accel-Buffering: no`). See [deployment](deployment.md).
