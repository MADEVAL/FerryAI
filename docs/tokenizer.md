# Tokenizer

Encode/decode text to token ids. FerryAI ships pure-PHP BPE and WordPiece tokenizers that load a
HuggingFace `tokenizer.json`, with an optional native tokenizers-cpp binding.

## Usage

```php
$tok = AI::tokenizer('/path/to/tokenizer.json');

$ids  = $tok->encode('Hello world');       // int[]
$text = $tok->decode($ids);                // string
```

Or build directly:

```php
use FerryAI\Tokenizer\TokenizerFactory;
$tok = (new TokenizerFactory())->createFromFile('/path/to/tokenizer.json');
```

See [`examples/02-tokenizer.php`](../examples/02-tokenizer.php).

## Types

`TokenizerFactory` reads `tokenizer.json` and picks an implementation:

- **BPE** → `PureBpeTokenizer` (GPT-2/Qwen-style merges).
- **WordPiece** → `PureWordPieceTokenizer` (BERT-style).

Both round-trip (`decode(encode(x))`) and support special tokens and chunking.

## Native binding (optional)

For maximum speed/fidelity, install the
[tokenizers-cpp](https://github.com/mlc-ai/tokenizers-cpp) shared library and set
`FERRY_AI_TOKENIZERS_LIB`. When present, `HuggingFaceTokenizer` (FFI) is used; otherwise the
pure-PHP tokenizers handle BPE/WordPiece. Unsupported tokenizer types without the native binding
raise a `TokenizerException` with actionable guidance.

## In embedding / pipelines

The tokenizer is resolved automatically for `AI::embed()` from the embedding model directory, and
used by the `TokenizeStage`/`ChunkStage` pipeline stages.
