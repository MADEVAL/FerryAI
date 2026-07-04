<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Double;

use FerryAI\LlamaBackend\LlamaContextParams;
use FerryAI\LlamaBackend\LlamaModelParams;
use FerryAI\LlamaBackend\Runtime\LlamaRuntimeInterface;
use FerryAI\LlamaBackend\Runtime\LlamaSession;

/**
 * Deterministic {@see LlamaRuntimeInterface} double.
 *
 * `evaluate()` returns logits whose argmax is the next scripted token (then EOS), so a
 * {@see \FerryAI\LlamaBackend\Sampling\GreedySampler} produces exactly the scripted sequence.
 */
final class MockLlamaRuntime implements LlamaRuntimeInterface
{
    /** @var list<array{path: string, model: LlamaModelParams, context: LlamaContextParams}> */
    public array $createdSessions = [];

    /**
     * @param list<int>          $scripted     token ids to emit in order, then EOS
     * @param array<int, string> $pieces       token id => detokenised piece
     * @param list<int>          $promptTokens tokens returned by tokenize()
     */
    public function __construct(
        private readonly bool $available = true,
        private readonly string $engineVersion = 'mock-llama-b1',
        private readonly bool $gpu = false,
        private readonly int $vocab = 32,
        private readonly int $ctx = 2048,
        private readonly int $embd = 8,
        private readonly int $eos = 2,
        private readonly array $scripted = [],
        private readonly array $pieces = [],
        private readonly array $promptTokens = [1, 5, 6],
    ) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function version(): string
    {
        return $this->engineVersion;
    }

    public function supportsGpu(): bool
    {
        return $this->gpu;
    }

    public function createSession(
        string $modelPath,
        LlamaModelParams $modelParams,
        LlamaContextParams $contextParams,
    ): LlamaSession {
        $this->createdSessions[] = ['path' => $modelPath, 'model' => $modelParams, 'context' => $contextParams];

        return new MockLlamaSession();
    }

    public function nVocab(LlamaSession $session): int
    {
        return $this->vocab;
    }

    public function nCtx(LlamaSession $session): int
    {
        return $this->ctx;
    }

    public function nEmbd(LlamaSession $session): int
    {
        return $this->embd;
    }

    public function eosToken(LlamaSession $session): int
    {
        return $this->eos;
    }

    /**
     * @return list<int>
     */
    public function tokenize(LlamaSession $session, string $text, bool $addBos = true, bool $special = true): array
    {
        return $this->promptTokens;
    }

    public function tokenToPiece(LlamaSession $session, int $token): string
    {
        return $this->pieces[$token] ?? ('<' . $token . '>');
    }

    /**
     * @param list<int> $tokens
     *
     * @return list<float>
     */
    public function evaluate(LlamaSession $session, array $tokens, int $nPast): array
    {
        $mock = $this->session($session);
        $target = $mock->cursor < \count($this->scripted) ? $this->scripted[$mock->cursor] : $this->eos;
        ++$mock->cursor;

        $logits = array_fill(0, $this->vocab, 0.0);
        $logits[$target] = 10.0;

        return $logits;
    }

    public function resetState(LlamaSession $session): void
    {
        $this->session($session)->cursor = 0;
    }

    public function releaseSession(LlamaSession $session): void
    {
        $this->session($session)->released = true;
    }

    private function session(LlamaSession $session): MockLlamaSession
    {
        if (!$session instanceof MockLlamaSession) {
            throw new \InvalidArgumentException('MockLlamaRuntime requires a MockLlamaSession.');
        }

        return $session;
    }
}
