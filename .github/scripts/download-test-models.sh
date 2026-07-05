#!/bin/bash
# Download test models for integration testing
# Run: .github/scripts/download-test-models.sh

set -e

MODEL_DIR="${MODEL_DIR:-tests/fixtures/models}"
mkdir -p "$MODEL_DIR"

echo "Downloading test models..."

# all-MiniLM-L6-v2 ONNX (for embedding tests)
echo "  all-MiniLM-L6-v2..."
# Requires huggingface-cli or manual download

echo "Done. Models in $MODEL_DIR"
