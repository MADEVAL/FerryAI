# Safetensors → GGUF conversion guide

FerryAI runs inference through llama.cpp (GGUF) and ONNX Runtime (ONNX). Many models
on HuggingFace ship as **safetensors** — a weight-only format. Safetensors files contain
no compute graph, no tokenizer metadata, and no architecture definition. To use them with
FerryAI you must convert them to GGUF first.

## What safetensors is (and isn't)

Safetensors stores only the numeric weight matrices. It does **not** contain:
- Model architecture (layer counts, attention type, normalization)
- Tokenizer vocabulary
- ONNX compute graph

This is why a loader for safetensors alone cannot do inference — the weights are
meaningless without the architecture. Conversion is required.

## The conversion toolchain

llama.cpp ships `convert_hf_to_gguf.py` — a Python script that reads HuggingFace-format
models (safetensors or PyTorch `.bin`) and writes a GGUF file. FerryAI already knows how
to load GGUF through `LlamaBackend`.

The script lives in the llama.cpp source tree (`/path/to/llama-src/convert_hf_to_gguf.py`;
cloned with `git clone https://github.com/ggml-org/llama.cpp`).

> To add a new architecture, submit a converter to the llama.cpp project. The conversion
> directory already supports **82 model families** (Qwen, Llama, Mistral, Phi, Gemma,
> DeepSeek, Falcon, GPT-2, BERT, …).

## Prerequisites

```bash
# Python 3.10+
python3 --version      # must be >= 3.10

# Install dependencies (user-space, no sudo)
pip install --user torch safetensors transformers numpy

# Verify
python3 -c "import torch; import safetensors; print('OK')"
```

## Conversion — step by step

### 1. Locate your model

Your HuggingFace model directory must contain at least:
- `model.safetensors` (or a sharded set `model-00001-of-00002.safetensors`, ...)
- `config.json` (the architecture definition)

```bash
ls Qwen3-0.6B/
# → config.json  model.safetensors  tokenizer.json  tokenizer_config.json  vocab.json  merges.txt
```

### 2. Run the converter

```bash
python3 /path/to/llama-src/convert_hf_to_gguf.py \
  /path/to/models/Qwen3-0.6B \
  --outtype f16 \
  --outfile /path/to/models/qwen3-0.6b-f16.gguf
```

**Common options:**

| Flag | Effect |
|------|--------|
| `--outtype f16` | Float16 weights (recommended for GPU) |
| `--outtype q8_0` | 8-bit quantized (smaller, CPU-friendly) |
| `--outtype q4_k_m` | 4-bit quantized (very small, good quality) |
| `--outfile <path>` | Output GGUF path |
| `--vocab-only` | Export only the tokenizer (for testing) |
| `--bigendian` | Big-endian output (rare) |

For a list of all supported architectures:
```bash
python3 /path/to/llama-src/convert_hf_to_gguf.py --list
```

### 3. Verify the GGUF

```bash
# Check metadata
python3 -c "
from gguf import GGUFReader
r = GGUFReader('/path/to/models/qwen3-0.6b-f16.gguf')
for k in r.fields: print(k, '=', r.fields[k])
"

# Quick test with llama-cli (CPU)
/path/to/llama/llama-cli --model /path/to/models/qwen3-0.6b-f16.gguf -p "Hello" -n 10
```

### 4. Use with FerryAI

```php
// Set the GGUF path and point at the llama wrapper
putenv('FERRY_AI_LLAMA_WRAPPER=/path/to/llama/ferry_llama.so');
putenv('FERRY_AI_LLAMA_MODEL=/path/to/models/qwen3-0.6b-f16.gguf');

FerryAI\AI::config([
    'backend' => 'llama',
    'device'  => 'cuda',                    // or 'cpu'
    'backends' => ['llama' => ['model_path' => '/path/to/models/qwen3-0.6b-f16.gguf']],
]);

$result = FerryAI\AI::chat([['role' => 'user', 'content' => 'What is PHP?']]);
echo $result->text;
```

Or use the examples (they auto-detect the model from `FERRY_AI_LLAMA_MODEL`):

```bash
export FERRY_AI_LLAMA_DIR=/path/to/llama-cuda
export FERRY_AI_LLAMA_MODEL=/path/to/models/qwen3-0.6b-f16.gguf
export FERRY_AI_LLAMA_DEVICE=cuda
php examples/03-chat.php
```

## Converting ONNX models

Some HuggingFace models can be exported to ONNX. This is separate from the GGUF path:

```bash
# Install optimum (ONNX export toolkit)
pip install --user optimum onnx onnxruntime

# Export
python3 -m optimum.exporters.onnx \
  --model /path/to/models/all-MiniLM-L6-v2-onnx \
  --task feature-extraction \
  /path/to/models/all-MiniLM-L6-v2-onnx-exported
```

The resulting `.onnx` file works with `FerryAI\OnnxBackend\OnnxBackend`.

> ONNX export is model-specific and requires the architecture to be supported by
> HuggingFace `optimum`. Not all models can be exported.

## Introspecting safetensors from PHP

FerryAI includes `SafetensorsInspector` — a pure-PHP tool that reads the safetensors
header and reports tensor names, shapes, dtypes and sizes **without loading weights
into memory**. Useful for Model Hub "what is inside this file" checks.

```php
use FerryAI\ModelHub\Format\SafetensorsInspector;

$info = SafetensorsInspector::inspect('/path/to/model.safetensors');
echo $info['tensor_count'];  // e.g. 291
echo $info['tensors']['model.embed_tokens.weight']['shape'];  // [151936, 1024]
```

## Troubleshooting

| Problem | Solution |
|---------|----------|
| `ModuleNotFoundError: torch` | `pip install --user torch` |
| `KeyError: 'qwen2'` or unknown architecture | Update llama.cpp: `cd /path/to/llama-src && git pull` |
| `CUDA out of memory` during conversion | Conversion is CPU-only (no GPU needed). Reduce `--outtype` to `q4_k_m` for smaller output. |
| GGUF will not load in llama.cpp | Verify with `llama-cli --model <file> -p "test" -n 1`. Check the GGUF version compatibility. |
| Sharded safetensors (multiple files) | The converter handles shards automatically — just point at the directory containing `model-*-of-*.safetensors`. |
