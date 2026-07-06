# Streaming

Generate LLM output token-by-token and expose it as HTTP-friendly streams. Built on PHP
generators for maximum memory efficiency.

## Token stream

`AI::stream()` returns a `Generator<int, string>` of decoded pieces:

```php
foreach (AI::stream([['role' => 'user', 'content' => 'Count to 5']]) as $piece) {
    echo $piece;          // 1 2 3 4 5
    @ob_flush(); @flush();
}
```

Requires the llama backend — see [backends/llama](backends/llama.md). Options are the same
as `AI::chat()`:

```php
AI::stream($messages, [
    'temperature' => 0,
    'max_tokens'  => 100,
    'sampler'     => 'greedy',
]);
// Or with grammar-constrained output:
AI::stream($messages, [
    'grammar' => 'root ::= "yes" | "no"',
]);
```

## Raw SSE / NDJSON

`FerryAI\StreamResponse` formats a token iterable without any HTTP dependency:

```php
$sr = new FerryAI\StreamResponse(AI::stream($messages));

echo $sr->toSse();          // "data: piece1\n\ndata: piece2\n\n…"
echo $sr->toNdjson();       // {"token":"piece1"}\n{"token":"piece2"}\n…
```

See [`examples/04-streaming.php`](../examples/04-streaming.php),
[`examples/18-stream-response.php`](../examples/18-stream-response.php).

## PSR-7 response

`AI::streamResponse()` (or `StreamResponse::create()`) returns a PSR-7 `ResponseInterface`
with `Content-Type: text/event-stream`, when a PSR-17 factory is installed
(`nyholm/psr7` or `guzzlehttp/psr7`):

```php
use FerryAI\StreamResponse;

$response = StreamResponse::create(AI::stream($messages));
$response->getStatusCode();                 // 200
$response->getHeaderLine('Content-Type');   // text/event-stream
(string) $response->getBody();              // SSE body

// Or use the facade shortcut:
$response = AI::streamResponse($messages);
```

Without a PSR-17 factory, `streamResponse()` throws a clear error — use `toSse()` or
`toNdjson()` instead.

## Laravel / Symfony integration

In Laravel, return the stream response from a controller directly:

```php
use FerryAI\AI;

class ChatController
{
    public function stream(Request $request)
    {
        $messages = [['role' => 'user', 'content' => $request->input('prompt')]];
        return AI::streamResponse($messages);
    }
}
```

## Real-time flushing

For live streaming to a browser:
1. Disable output buffering (`ob_end_flush()` before the loop).
2. Flush after each piece (`@ob_flush(); @flush()`).
3. Behind Nginx/FPM, disable proxy buffering:
   ```
   proxy_buffering off;
   fastcgi_buffering off;
   ```
   Or send header `X-Accel-Buffering: no`.

## ChatFormatter templates

`ChatFormatter` converts chat message arrays into the format that the LLM expects.
Five templates are supported:

| Template | Prompt format | Models |
|----------|--------------|--------|
| `chatml` (default) | `<|im_start|>role\ncontent<|im_end|>` | Qwen, Phi |
| `llama3` | `<|begin_of_text|><|start_header_id|>role<|end_header_id|>\n\ncontent<|eot_id|>` | LLaMA 3 |
| `vicuna` | `USER: content\nASSISTANT: content` | Vicuna |
| `alpaca` | `### Instruction:\ncontent\n### Response:\ncontent` | Alpaca |
| `mistral` | `[INST] content [/INST] content` | Mistral |

Select via `backends.llama.chat_template` config or let `ChatFormatter` auto-detect from
the GGUF metadata.

## Streaming with grammar

Grammar-constrained streaming ensures every token conforms to a GBNF grammar or JSON Schema.
Tokens are validated incrementally by `GbnfMatcher`:

```php
// GBNF
AI::stream($messages, ['grammar' => 'root ::= "yes" | "no"']);

// JSON Schema → GBNF (auto-converted)
AI::stream($messages, [
    'sampler' => 'grammar',
    'grammar' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'age'  => ['type' => 'integer'],
        ],
        'required' => ['name', 'age'],
    ],
]);
```

See [`examples/09-grammar.php`](../examples/09-grammar.php).
