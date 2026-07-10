# ferry-ai/inference-tokenizer

Tokenizers for [FerryAI](https://github.com/MADEVAL/FerryAI), the inference-only runtime for PHP 8.3+.
Ships pure-PHP BPE and WordPiece implementations plus a native HuggingFace tokenizer binding.

## Installation

```bash
composer require ferry-ai/inference-tokenizer
```

## What's inside

- **`TokenizerFactory`** — builds a tokenizer from a `tokenizer.json` or a `TokenizerType`.
- **`PureBpeTokenizer`**, **`PureWordPieceTokenizer`** — dependency-free encoders/decoders.
- **`HuggingFaceTokenizer`** — native `libtokenizers_cpp` binding via FFI for full fidelity.

The pure-PHP tokenizers work with no native dependency; the native binding is used only when
`FERRY_AI_TOKENIZERS_LIB` points to `libtokenizers_cpp`.

## Requirements

- PHP >= 8.3
- `ferry-ai/inference-core`
- `ext-ffi` at runtime (only for the native HuggingFace tokenizer)

## License

MIT — see [LICENSE](https://github.com/MADEVAL/FerryAI/blob/main/LICENSE.md).

Full documentation: [docs/tokenizer.md](https://github.com/MADEVAL/FerryAI/blob/main/docs/tokenizer.md).
