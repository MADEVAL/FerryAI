<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\FFI;

/**
 * Wrapper over llama_batch for batched token evaluation.
 *
 * FFI boundary (excluded from static analysis). Building a native llama_batch requires the exact
 * struct ABI of the target llama.cpp build; this wrapper accumulates tokens/positions and is
 * finalised by a validated native binding.
 */
final class LlamaBatch
{
    /** @var list<int> */
    private array $tokens = [];

    /** @var list<int> */
    private array $positions = [];

    public function add(int $token, int $position): void
    {
        $this->tokens[] = $token;
        $this->positions[] = $position;
    }

    /**
     * @param list<int> $tokens
     */
    public function addSequence(array $tokens, int $startPos = 0): void
    {
        foreach ($tokens as $offset => $token) {
            $this->add($token, $startPos + $offset);
        }
    }

    public function clear(): void
    {
        $this->tokens = [];
        $this->positions = [];
    }

    public function size(): int
    {
        return \count($this->tokens);
    }

    /**
     * @return list<int>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }
}
