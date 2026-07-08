<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Runtime;

use FerryAI\LlamaBackend\FFI\FerryLlama;
use FerryAI\LlamaBackend\LlamaContextParams;
use FerryAI\LlamaBackend\LlamaModelParams;

/**
 * Production {@see LlamaRuntimeInterface} backed by llama.cpp through the flat
 * `ferry_llama` wrapper (see native/llama-wrapper/README.md). Real CPU + GPU inference.
 *
 * Excluded from static analysis (FFI boundary). Standalone-process only — under
 * PHPUnit the ggml global constructors conflict with the test runner, so unit
 * tests use a mock runtime and the integration test runs in a subprocess.
 *
 * isAvailable() only checks that ext-ffi and the wrapper DLL are present; the DLL
 * is loaded lazily on the first createSession() so probing stays cheap and safe.
 */
final class NativeLlamaRuntime implements LlamaRuntimeInterface
{
    private ?FerryLlama $ffi = null;

    private ?bool $supportsGpu = null;

    public function isAvailable(): bool
    {
        return \extension_loaded('FFI') && FerryLlama::resolveWrapperPath() !== null;
    }

    public function version(): string
    {
        return 'llama.cpp (ferry_llama wrapper)';
    }

    public function supportsGpu(): bool
    {
        if ($this->supportsGpu === null) {
            $this->supportsGpu = $this->ffi()->supportsGpu();
        }

        return $this->supportsGpu;
    }

    public function createSession(
        string $modelPath,
        LlamaModelParams $modelParams,
        LlamaContextParams $contextParams,
    ): LlamaSession {
        $ffi = $this->ffi();
        $model = $ffi->loadModel($modelPath, $modelParams->nGpuLayers);
        $threads = $contextParams->nThreads > 0 ? $contextParams->nThreads : 4;

        try {
            $context = $ffi->newContext($model, $contextParams->nCtx, $threads);

            return new NativeLlamaSession(
                $ffi,
                $model,
                $context,
                $ffi->nVocab($model),
                $ffi->nCtx($context),
                $ffi->eosToken($model),
            );
        } catch (\Throwable $e) {
            $ffi->freeModel($model);

            throw $e;
        }
    }

    public function nVocab(LlamaSession $session): int
    {
        return $this->native($session)->nVocab;
    }

    public function nCtx(LlamaSession $session): int
    {
        return $this->native($session)->nCtx;
    }

    public function nEmbd(LlamaSession $session): int
    {
        $s = $this->native($session);

        return $s->ffi->nEmbd($s->model);
    }

    public function eosToken(LlamaSession $session): int
    {
        return $this->native($session)->eosToken;
    }

    /**
     * @return list<int>
     */
    public function tokenize(LlamaSession $session, string $text, bool $addBos = true): array
    {
        $s = $this->native($session);

        return $s->ffi->tokenize($s->model, $text, $addBos);
    }

    public function tokenToPiece(LlamaSession $session, int $token): string
    {
        $s = $this->native($session);

        return $s->ffi->tokenToPiece($s->model, $token);
    }

    /**
     * @param list<int> $tokens
     *
     * @return list<float>
     */
    public function evaluate(LlamaSession $session, array $tokens): array
    {
        $s = $this->native($session);

        return $s->ffi->eval($s->context, $s->model, $tokens, $s->nVocab);
    }

    /**
     * @param list<int> $tokens
     *
     * @return array<int, float>
     */
    public function evaluateTopK(LlamaSession $session, array $tokens, int $k): array
    {
        $s = $this->native($session);

        return $s->ffi->evalTopK($s->context, $s->model, $tokens, $k);
    }

    public function resetState(LlamaSession $session): void
    {
        $s = $this->native($session);
        $s->ffi->reset($s->context);
    }

    public function releaseSession(LlamaSession $session): void
    {
        $s = $this->native($session);
        $s->ffi->freeContext($s->context);
        $s->ffi->freeModel($s->model);
    }

    private function ffi(): FerryLlama
    {
        if ($this->ffi === null) {
            $wrapper = FerryLlama::resolveWrapperPath();

            if ($wrapper === null) {
                throw new \RuntimeException(
                    'ferry_llama wrapper not found. Set FERRY_AI_LLAMA_WRAPPER to ferry_llama.dll, '
                    . 'or FERRY_AI_LLAMA_LIB to llama.dll in the same directory. '
                    . 'Build it with native/llama-wrapper/build.ps1.',
                );
            }

            $this->ffi = new FerryLlama($wrapper, \dirname($wrapper));
        }

        return $this->ffi;
    }

    private function native(LlamaSession $session): NativeLlamaSession
    {
        if (!$session instanceof NativeLlamaSession) {
            throw new \InvalidArgumentException('NativeLlamaRuntime requires a NativeLlamaSession.');
        }

        return $session;
    }
}
