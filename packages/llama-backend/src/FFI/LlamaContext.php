<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\FFI;

use FerryAI\LlamaBackend\LlamaContextParams;
use FerryAI\LlamaBackend\LlamaModelParams;

/**
 * High-level wrapper over a llama_model + llama_context.
 *
 * FFI boundary (excluded from static analysis). The model/context params are struct-by-value in the
 * C API and their layout varies between llama.cpp builds, so the load/tokenize/decode operations are
 * completed by a binding validated against the target library (integration suite). Until then these
 * operations raise a clear message; the pure-PHP orchestration and mock runtime remain fully usable.
 */
final class LlamaContext
{
    private const string REQUIRES_BINDING = 'The native llama.cpp generation binding must be validated against '
        . 'your specific library ABI (model/context params are struct-by-value). Use MockLlamaRuntime for '
        . 'development/testing, or wire a validated binding for production inference.';

    public function __construct(
        private readonly string $modelPath,
        private readonly LlamaModelParams $modelParams,
        private readonly LlamaContextParams $contextParams,
        private readonly LlamaCpp $llama = new LlamaCpp(),
    ) {}

    public function modelPath(): string
    {
        return $this->modelPath;
    }

    public function modelParams(): LlamaModelParams
    {
        return $this->modelParams;
    }

    public function contextParams(): LlamaContextParams
    {
        return $this->contextParams;
    }

    public function llama(): LlamaCpp
    {
        return $this->llama;
    }

    public function nVocab(): int
    {
        throw new \RuntimeException(self::REQUIRES_BINDING);
    }

    public function nCtx(): int
    {
        return $this->contextParams->nCtx;
    }

    public function nEmbd(): int
    {
        throw new \RuntimeException(self::REQUIRES_BINDING);
    }

    public function eosToken(): int
    {
        throw new \RuntimeException(self::REQUIRES_BINDING);
    }

    /**
     * @return list<int>
     */
    public function tokenize(string $text, bool $addBos = true, bool $special = true): array
    {
        throw new \RuntimeException(self::REQUIRES_BINDING);
    }

    public function tokenToPiece(int $token): string
    {
        throw new \RuntimeException(self::REQUIRES_BINDING);
    }

    /**
     * @param list<int> $tokens
     *
     * @return list<float>
     */
    public function evaluate(array $tokens, int $nPast): array
    {
        throw new \RuntimeException(self::REQUIRES_BINDING);
    }

    public function resetState(): void
    {
        // KV cache reset requires the native binding; no-op until then.
    }
}
