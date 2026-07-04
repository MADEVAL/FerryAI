<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Runtime;

use FerryAI\LlamaBackend\FFI\LlamaContext;
use FerryAI\LlamaBackend\FFI\LlamaCpp;
use FerryAI\LlamaBackend\LlamaContextParams;
use FerryAI\LlamaBackend\LlamaModelParams;

/**
 * Production {@see LlamaRuntimeInterface} backed by llama.cpp via FFI.
 *
 * Excluded from static analysis (FFI boundary). The raw llama.cpp ABI binding (struct-by-value
 * params) is target-specific and validated by the integration suite; until a validated binding is
 * wired {@see isAvailable()} reports false, so the backend cleanly reports unavailability and the
 * fully-tested pure-PHP orchestration / mock runtime are used for development.
 */
final class NativeLlamaRuntime implements LlamaRuntimeInterface
{
    private readonly LlamaCpp $llama;

    public function __construct(?LlamaCpp $llama = null)
    {
        $this->llama = $llama ?? new LlamaCpp();
    }

    public function isAvailable(): bool
    {
        // A complete, ABI-validated generation binding is required before enabling native inference.
        return false;
    }

    public function version(): string
    {
        return $this->llama->version();
    }

    public function supportsGpu(): bool
    {
        return false;
    }

    public function createSession(
        string $modelPath,
        LlamaModelParams $modelParams,
        LlamaContextParams $contextParams,
    ): LlamaSession {
        return new NativeLlamaSession(new LlamaContext($modelPath, $modelParams, $contextParams, $this->llama));
    }

    public function nVocab(LlamaSession $session): int
    {
        return $this->context($session)->nVocab();
    }

    public function nCtx(LlamaSession $session): int
    {
        return $this->context($session)->nCtx();
    }

    public function nEmbd(LlamaSession $session): int
    {
        return $this->context($session)->nEmbd();
    }

    public function eosToken(LlamaSession $session): int
    {
        return $this->context($session)->eosToken();
    }

    /**
     * @return list<int>
     */
    public function tokenize(LlamaSession $session, string $text, bool $addBos = true, bool $special = true): array
    {
        return $this->context($session)->tokenize($text, $addBos, $special);
    }

    public function tokenToPiece(LlamaSession $session, int $token): string
    {
        return $this->context($session)->tokenToPiece($token);
    }

    /**
     * @param list<int> $tokens
     *
     * @return list<float>
     */
    public function evaluate(LlamaSession $session, array $tokens, int $nPast): array
    {
        return $this->context($session)->evaluate($tokens, $nPast);
    }

    public function resetState(LlamaSession $session): void
    {
        $this->context($session)->resetState();
    }

    public function releaseSession(LlamaSession $session): void
    {
        // The underlying context frees native resources on destruction.
    }

    private function context(LlamaSession $session): LlamaContext
    {
        if (!$session instanceof NativeLlamaSession) {
            throw new \InvalidArgumentException('NativeLlamaRuntime requires a NativeLlamaSession.');
        }

        return $session->context();
    }
}
