#!/usr/bin/env bash
#
# Build ferry_llama.{so,dylib} — the flat C wrapper around llama.cpp for PHP FFI.
# Linux/macOS counterpart of build.ps1. See README.md for why the wrapper exists.
#
# Prerequisites (all in $LLAMA_DIR, default: dir of this script's sibling libs):
#   - llama.cpp shared libs:  libllama.so, libggml.so, libggml-base.so, libggml-cpu*.so
#     (+ libggml-cuda.so for GPU). Linux build: https://github.com/ggml-org/llama.cpp/releases
#     or built from source (cmake).
#   - Matching headers next to the libs: llama.h, ggml.h, ggml-cpu.h, ggml-backend.h,
#     ggml-alloc.h, ggml-opt.h, gguf.h.
#   - A C compiler (cc/gcc/clang).
#
# Usage:
#   native/llama-wrapper/build.sh [LLAMA_DIR] [OUTPUT]
#   LLAMA_DIR defaults to $FERRY_AI_LLAMA_DIR or ~/llama
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC="$SCRIPT_DIR/ferry_llama.c"

LLAMA_DIR="${1:-${FERRY_AI_LLAMA_DIR:-$HOME/llama}}"

case "$(uname -s)" in
    Darwin) EXT="dylib"; RPATH_FLAG="-Wl,-rpath,@loader_path" ;;
    *)      EXT="so";    RPATH_FLAG="-Wl,-rpath,\$ORIGIN" ;;
esac

OUT="${2:-$LLAMA_DIR/ferry_llama.$EXT}"
CC="${CC:-cc}"

if [ ! -f "$SRC" ]; then echo "source not found: $SRC" >&2; exit 1; fi
if [ ! -d "$LLAMA_DIR" ]; then echo "LLAMA_DIR not found: $LLAMA_DIR" >&2; exit 1; fi

echo "Building $OUT (CC=$CC, LLAMA_DIR=$LLAMA_DIR)"

"$CC" -O2 -fPIC -shared \
    -I"$LLAMA_DIR" \
    "$SRC" \
    -o "$OUT" \
    -L"$LLAMA_DIR" -lllama -lggml \
    "$RPATH_FLAG"

echo "OK: $OUT"
