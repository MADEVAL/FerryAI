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

// Special tokens
$tok->bosId();     // e.g. 101
$tok->eosId();     // e.g. 102
$tok->padId();     // e.g. 0
$tok->unkId();     // e.g. 100
$tok->vocabSize(); // e.g. 30522
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
    public function encode(string $text, ?array $options = null): array;
    public function decode(array $ids): string;
    public function encodeBatch(array $texts): array;
    public function vocabSize(): int;
    public function tokenizerType(): TokenizerType;
    public function bosToken(): string;
    public function eosToken(): string;
    public function bosId(): int;
    public function eosId(): int;
    public function padId(): int;
    public function unkId(): int;
}
```

## Native binding (optional)

For maximum speed and fidelity, install the
[tokenizers-cpp](https://github.com/mlc-ai/tokenizers-cpp) shared library and set
`FERRY_AI_TOKENIZERS_LIB=/path/to/libtokenizer.so`. When present,
`HuggingFaceTokenizer` wraps the native library via FFI; otherwise pure-PHP tokenizers cover
BPE and WordPiece. Unsupported types (e.g. Unigram, SentencePiece) without the native binding
raise `TokenizerException` with actionable guidance.

The `TokenizerLoader` class handles detection of the tokenizer type from the JSON structure.

## Special tokens

```php
use FerryAI\Tokenizer\SpecialTokens;

$st = SpecialTokens::fromTokenizer($tok);
$st->bos();    // '[CLS]' or '<s>' depending on the vocab
$st->eos();    // '[SEP]' or '</s>'
$st->pad();    // '[PAD]' or '<pad>'
$st->unk();    // '[UNK]' or '<unk>'
```

## In embedding / pipelines

The tokenizer is resolved automatically for `AI::embed()` from the embedding model directory,
and used by the `TokenizeStage` and `ChunkStage` pipeline stages. Round-tripping
(`decode(encode(x))`) preserves the original text including special tokens.
