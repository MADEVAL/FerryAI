<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Runtime;

use FerryAI\LlamaBackend\LlamaContextParams;
use FerryAI\LlamaBackend\LlamaModelParams;

/**
 * Thin seam over llama.cpp.
 *
 * The single FFI boundary of the package: production uses `NativeLlamaRuntime`, unit tests inject a
 * mock. Only plain PHP values (token ids, logits, strings) cross the seam, so the generation loop in
 * `LlamaModel` is fully unit-testable without the native library.
 */
interface LlamaRuntimeInterface
{
    public function isAvailable(): bool;

    public function version(): string;

    /**
     * Whether the loaded llama.cpp build offloads to a GPU.
     */
    public function supportsGpu(): bool;

    public function createSession(
        string $modelPath,
        LlamaModelParams $modelParams,
        LlamaContextParams $contextParams,
    ): LlamaSession;

    public function nVocab(LlamaSession $session): int;

    public function nCtx(LlamaSession $session): int;

    public function nEmbd(LlamaSession $session): int;

    public function eosToken(LlamaSession $session): int;

    /**
     * @return list<int>
     */
    public function tokenize(LlamaSession $session, string $text, bool $addBos = true): array;

    public function tokenToPiece(LlamaSession $session, int $token): string;

    /**
     * Evaluates the given tokens and returns the logits (length = vocab size) for the next position.
     * The native runtime tracks its own KV-cache position; call {@see resetState()} to rewind.
     *
     * @param list<int> $tokens
     *
     * @return list<float>
     */
    public function evaluate(LlamaSession $session, array $tokens): array;

    /**
     * Like {@see evaluate()} but returns only the top-k tokens by logit as a sparse
     * `token id => logit` map — the heavy per-token work over the full vocab stays native.
     *
     * @param list<int> $tokens
     *
     * @return array<int, float>
     */
    public function evaluateTopK(LlamaSession $session, array $tokens, int $k): array;

    /**
     * Clears the KV cache so a new sequence can be generated.
     */
    public function resetState(LlamaSession $session): void;

    public function releaseSession(LlamaSession $session): void;
}
