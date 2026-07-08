#!/usr/bin/env bash
#
# package-release.sh — Build ferry_llama.so and stage it + SHA256 under
# native-binaries/<target>/
#
# Usage:
#   native/llama-wrapper/package-release.sh /path/to/llama
#   native/llama-wrapper/package-release.sh /path/to/llama linux-x86_64
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
LLAMA_DIR="${1:?usage: package-release.sh <llama-dir> [target]}"
TARGET="${2:-linux-x86_64}"
OUT_DIR="$REPO_DIR/native-binaries/$TARGET"

case "$(uname -s)" in
    Darwin) EXT="dylib"; TARGET="${TARGET:-macos-$(uname -m)}" ;;
    *)      EXT="so" ;;
esac

SRC="$REPO_DIR/native/llama-wrapper/ferry_llama.c"
DLL_NAME="ferry_llama-$TARGET.$EXT"
DEST="$OUT_DIR/$DLL_NAME"

mkdir -p "$OUT_DIR"

echo "=== Build $EXT wrapper ==="
"$SCRIPT_DIR/build.sh" "$LLAMA_DIR" "$DEST"

echo "=== Staged $DLL_NAME ==="
echo "$(stat -c%s "$DEST") bytes"

HASH=$(sha256sum "$DEST" | awk '{print $1}')
echo "$HASH  $DLL_NAME" > "$OUT_DIR/$DLL_NAME.sha256"
echo "=== SHA256 $HASH ==="

echo "=== Package complete: $DEST ==="
