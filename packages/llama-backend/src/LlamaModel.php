<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend;

use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\InferenceException;
use FerryAI\Core\ValueObjects\ChatMessage;
use FerryAI\Core\ValueObjects\GenerationResult;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Runtime\LlamaRuntimeInterface;
use FerryAI\LlamaBackend\Runtime\LlamaSession;
use FerryAI\LlamaBackend\Sampling\GrammarSampler;
use FerryAI\LlamaBackend\Sampling\Sampler;
use FerryAI\LlamaBackend\Sampling\SamplerFactory;
use FerryAI\LlamaBackend\Sampling\SamplerMath;

/**
 * A loaded llama.cpp model.
 *
 * Because a single `run()` is unrealistic for autoregressive generation, this class provides three
 * entry points: {@see run()} (one token), {@see runComplete()} (full response) and
 * {@see runStream()} (a Generator of pieces). The generation loop is pure PHP over the runtime seam.
 */
final class LlamaModel implements Model
{
    /** Number of recent tokens considered when applying repetition/frequency/presence penalties. */
    private const int PENALTY_WINDOW = 64;

    private bool $unloaded = false;

    public function __construct(
        private ?LlamaSession $session,
        private readonly LlamaRuntimeInterface $runtime,
        private readonly ChatFormatter $formatter,
        private readonly ?Sampler $sampler = null,
        private readonly ModelMetadata $metadata = new ModelMetadata('llama', '1.0', '', '', [], 0),
        private readonly Device $deviceType = Device::CPU,
        private readonly SamplingParams $defaultParams = new SamplingParams(),
        private readonly SamplerFactory $samplerFactory = new SamplerFactory(),
        private readonly ?GbnfGrammar $grammar = null,
    ) {}

    /**
     * Generates a single token.
     *
     * @param array<array-key, mixed> $inputs
     *
     * @return array{text: string, token: int}
     */
    #[\Override]
    public function run(array $inputs): array
    {
        $sampler = $this->resolveSampler($this->defaultParams, null);
        [, $logits] = $this->open($inputs, $sampler, $this->defaultParams);
        $token = $sampler->sample($logits, $this->defaultParams);

        return ['text' => $this->runtime->tokenToPiece($this->requireSession(), $token), 'token' => $token];
    }

    /**
     * Generates a full response. An explicit $sampler overrides per-request selection.
     *
     * @param array<array-key, mixed> $inputs
     */
    public function runComplete(array $inputs, ?SamplingParams $params = null, ?Sampler $sampler = null): GenerationResult
    {
        $params ??= $this->defaultParams;
        $sampler = $this->resolveSampler($params, $sampler);
        $start = microtime(true);

        [$promptTokens, $logits] = $this->open($inputs, $sampler, $params);

        $text = '';
        $generated = 0;

        foreach ($this->decodeLoop($promptTokens, $logits, $sampler, $params) as $token) {
            $text .= $this->runtime->tokenToPiece($this->requireSession(), $token);
            ++$generated;
        }

        $promptCount = \count($promptTokens);

        return new GenerationResult(
            text: $text,
            tokensGenerated: $generated,
            tokensPrompt: $promptCount,
            tokensTotal: $promptCount + $generated,
            durationMs: (microtime(true) - $start) * 1000.0,
        );
    }

    /**
     * Streams generated pieces. An explicit $sampler overrides per-request selection.
     *
     * @param array<array-key, mixed> $inputs
     *
     * @return \Generator<int, string>
     */
    public function runStream(array $inputs, ?SamplingParams $params = null, ?Sampler $sampler = null): \Generator
    {
        $params ??= $this->defaultParams;
        $sampler = $this->resolveSampler($params, $sampler);

        [$promptTokens, $logits] = $this->open($inputs, $sampler, $params);

        foreach ($this->decodeLoop($promptTokens, $logits, $sampler, $params) as $token) {
            yield $this->runtime->tokenToPiece($this->requireSession(), $token);
        }
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    #[\Override]
    public function inputs(): array
    {
        return ['messages' => ['name' => 'messages', 'shape' => [-1], 'dtype' => 'string']];
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    #[\Override]
    public function outputs(): array
    {
        return ['text' => ['name' => 'text', 'shape' => [-1], 'dtype' => 'string']];
    }

    #[\Override]
    public function metadata(): ModelMetadata
    {
        return $this->metadata;
    }

    #[\Override]
    public function device(): Device
    {
        return $this->deviceType;
    }

    #[\Override]
    public function unload(): void
    {
        if ($this->session !== null) {
            $this->runtime->releaseSession($this->session);
        }

        $this->session = null;
        $this->unloaded = true;
    }

    /**
     * Formats + tokenizes the prompt and evaluates it, returning [promptTokens, nextLogits].
     *
     * @param array<array-key, mixed> $inputs
     *
     * @return array{0: list<int>, 1: array<int, float>}
     */
    private function open(array $inputs, Sampler $sampler, SamplingParams $params): array
    {
        $session = $this->requireSession();
        $prompt = $this->formatter->format($this->messages($inputs));
        $promptTokens = $this->runtime->tokenize($session, $prompt, true, true);
        $this->runtime->resetState($session);
        $logits = $this->nextLogits($session, $promptTokens, 0, $sampler, $params);

        return [$promptTokens, $logits];
    }

    /**
     * @param list<int>         $promptTokens
     * @param array<int, float> $logits
     *
     * @return \Generator<int, int>
     */
    private function decodeLoop(array $promptTokens, array $logits, Sampler $sampler, SamplingParams $params): \Generator
    {
        $session = $this->requireSession();
        $nPast = \count($promptTokens);
        $eos = $this->runtime->eosToken($session);

        // Sliding window of recent tokens (prompt tail + generated) used for penalties.
        $recent = \array_slice($promptTokens, -self::PENALTY_WINDOW);

        for ($i = 0; $i < $params->maxTokens; ++$i) {
            $penalized = SamplerMath::applyPenalties(
                $logits,
                array_count_values($recent),
                $params->repetitionPenalty,
                $params->frequencyPenalty,
                $params->presencePenalty,
            );

            $token = $sampler->sample($penalized, $params);

            if ($token === $eos) {
                return;
            }

            yield $token;

            $recent[] = $token;

            if (\count($recent) > self::PENALTY_WINDOW) {
                array_shift($recent);
            }

            $logits = $this->nextLogits($session, [$token], $nPast, $sampler, $params);
            ++$nPast;
        }
    }

    /**
     * Grammar sampling needs the whole vocabulary; every other sampler works on a native
     * top-k pre-filter, which keeps PHP off the ~150k-token hot path.
     *
     * @param list<int> $tokens
     *
     * @return array<int, float>
     */
    private function nextLogits(LlamaSession $session, array $tokens, int $nPast, Sampler $sampler, SamplingParams $params): array
    {
        if ($sampler instanceof GrammarSampler) {
            return $this->runtime->evaluate($session, $tokens, $nPast);
        }

        $k = max(256, $params->topK);

        return $this->runtime->evaluateTopK($session, $tokens, $nPast, $k);
    }

    /**
     * An explicit override wins; otherwise an injected sampler; otherwise one picked from the
     * request parameters (grammar / greedy / nucleus) via the {@see SamplerFactory}.
     */
    private function resolveSampler(SamplingParams $params, ?Sampler $override): Sampler
    {
        $sampler = $override ?? $this->sampler ?? $this->samplerFactory->forParams($params, $this->grammar);

        if ($sampler instanceof GrammarSampler) {
            $session = $this->requireSession();
            $sampler = $sampler->bind(
                fn(int $token): string => $this->runtime->tokenToPiece($session, $token),
                $this->runtime->eosToken($session),
            );
        }

        return $sampler;
    }

    /**
     * @param array<array-key, mixed> $inputs
     *
     * @return array<int, ChatMessage|array{role?: string, content?: mixed}>
     */
    private function messages(array $inputs): array
    {
        $messages = \array_key_exists('messages', $inputs) ? $inputs['messages'] : $inputs;

        if (!\is_array($messages)) {
            throw new InferenceException('Chat input must be a list of messages.');
        }

        /** @var array<int, ChatMessage|array{role?: string, content?: mixed}> $messages */
        return array_values($messages);
    }

    private function requireSession(): LlamaSession
    {
        if ($this->unloaded || $this->session === null) {
            throw new InferenceException('Model has been unloaded; load the model again before running inference.');
        }

        return $this->session;
    }
}
