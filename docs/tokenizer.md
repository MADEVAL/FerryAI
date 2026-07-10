# Tokenizer

Encode/decode text to token IDs. FerryAI ships pure-PHP BPE and WordPiece tokenizers that
load a HuggingFace `tokenizer.json`, with an optional native tokenizers-cpp binding for
maximum speed.

## Usage via facade

```php
$tok = AI::tokenizer('/path/to/tokenizer.json');

$ids  = $tok->encode('Hello world');       // int[]
$text = $tok->decode($ids);                // string

// Batch encoding
$batch = $tok->encodeBatch(['Hello', 'World']);
```

## Usage directly

```php
use FerryAI\Tokenizer\TokenizerFactory;

$factory = new TokenizerFactory();
$tok = $factory->createFromFile('/path/to/tokenizer.json');

// Encode/decode
$ids  = $tok->encode('Hello world');
$text = $tok->decode([101, 7592, 2088, 102]);

// Special tokens (role-keyed: bos/eos/unk/pad/cls/sep/mask)
$tok->specialTokens();          // ['bos' => 1, 'eos' => 2, 'pad' => 0, ...]
$tok->specialTokenId('bos');    // e.g. 1  (null if the role is absent)
$tok->countTokens('Hello world'); // int — token count without materialising IDs
$tok->vocabSize();              // e.g. 30522
```

See [`examples/02-tokenizer.php`](../examples/02-tokenizer.php).

## Tokenizer types

`TokenizerFactory` reads the `tokenizer.json` and auto-selects the implementation:

| Type | Implementation | Models |
|------|---------------|--------|
| `BPE` | `PureBpeTokenizer` | GPT-2, Qwen, LLaMA, RoBERTa |
| `WordPiece` | `PureWordPieceTokenizer` | BERT, DistilBERT |

## Contract

```php
interface Tokenizer
{
    public function encode(string $text, bool $addSpecialTokens = true): array;
    public function decode(array $ids): string;
    public function encodeBatch(array $texts, bool $padToMaxLength = true): array;
    public function vocabSize(): int;
    public function type(): TokenizerType;
    public function specialTokenId(string $tokenName): ?int;
    public function specialTokens(): array;
    public function countTokens(string $text): int;
    public function chunk(string $text, int $maxTokens = 512, int $overlap = 64): array;
}
```

## Native binding (optional)

For maximum speed and fidelity, install the
[tokenizers-cpp](https://github.com/mlc-ai/tokenizers-cpp) shared library and set
`FERRY_AI_TOKENIZERS_LIB=/path/to/libtokenizer.so`. When present,
`HuggingFaceTokenizer` wraps the native library via FFI; otherwise pure-PHP tokenizers cover
BPE and WordPiece. Unsupported types (e.g. Unigram, SentencePiece) without the native binding
raise `TokenizerException` with actionable guidance.

The `TokenizerLoader` class handles detection of the tokenizer type from the JSON structure
(`loadFromFile()`, `loadFromModel()`, `detectType()`).

## Special tokens

`SpecialTokens::extract()` reads a decoded `tokenizer.json` config and returns a role-keyed
map of token IDs (`bos`, `eos`, `unk`, `pad`, `cls`, `sep`, `mask`). It is what the pure-PHP
tokenizers use to back `specialTokens()` / `specialTokenId()`:

```php
use FerryAI\Tokenizer\SpecialTokens;

$config = json_decode(file_get_contents('/path/to/tokenizer.json'), true);
$roles  = SpecialTokens::extract($config);
// e.g. ['bos' => 1, 'eos' => 2, 'unk' => 0]  — only roles present in the vocab

// On a tokenizer instance the same data is exposed as:
$tok->specialTokens();          // full role => id map
$tok->specialTokenId('eos');    // 2 (or null)
```

## In embedding / pipelines

The tokenizer is resolved automatically for `AI::embed()` from the embedding model directory,
and used by the `TokenizeStage` and `ChunkStage` pipeline stages. Round-tripping
(`decode(encode(x))`) preserves the original text including special tokens.
