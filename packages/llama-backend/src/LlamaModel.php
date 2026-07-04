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
use FerryAI\LlamaBackend\Runtime\LlamaRuntimeInterface;
use FerryAI\LlamaBackend\Runtime\LlamaSession;
use FerryAI\LlamaBackend\Sampling\GreedySampler;
use FerryAI\LlamaBackend\Sampling\Sampler;

/**
 * A loaded llama.cpp model.
 *
 * Because a single `run()` is unrealistic for autoregressive generation, this class provides three
 * entry points: {@see run()} (one token), {@see runComplete()} (full response) and
 * {@see runStream()} (a Generator of pieces). The generation loop is pure PHP over the runtime seam.
 */
final class LlamaModel implements Model
{
    private bool $unloaded = false;

    public function __construct(
        private ?LlamaSession $session,
        private readonly LlamaRuntimeInterface $runtime,
        private readonly ChatFormatter $formatter,
        private readonly Sampler $sampler = new GreedySampler(),
        private readonly ModelMetadata $metadata = new ModelMetadata('llama', '1.0', '', '', [], 0),
        private readonly Device $deviceType = Device::CPU,
        private readonly SamplingParams $defaultParams = new SamplingParams(),
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
        [, $logits] = $this->open($inputs);
        $token = $this->sampler->sample($logits, $this->defaultParams);

        return ['text' => $this->runtime->tokenToPiece($this->requireSession(), $token), 'token' => $token];
    }

    /**
     * Generates a full response.
     *
     * @param array<array-key, mixed> $inputs
     */
    public function runComplete(array $inputs, ?SamplingParams $params = null): GenerationResult
    {
        $params ??= $this->defaultParams;
        $start = microtime(true);

        [$promptTokens, $logits] = $this->open($inputs);

        $text = '';
        $generated = 0;

        foreach ($this->decodeLoop($promptTokens, $logits, $params) as $token) {
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
     * Streams generated pieces.
     *
     * @param array<array-key, mixed> $inputs
     *
     * @return \Generator<int, string>
     */
    public function runStream(array $inputs, ?SamplingParams $params = null): \Generator
    {
        $params ??= $this->defaultParams;

        [$promptTokens, $logits] = $this->open($inputs);

        foreach ($this->decodeLoop($promptTokens, $logits, $params) as $token) {
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
     * @return array{0: list<int>, 1: list<float>}
     */
    private function open(array $inputs): array
    {
        $session = $this->requireSession();
        $prompt = $this->formatter->format($this->messages($inputs));
        $promptTokens = $this->runtime->tokenize($session, $prompt, true, true);
        $this->runtime->resetState($session);
        $logits = $this->runtime->evaluate($session, $promptTokens, 0);

        return [$promptTokens, $logits];
    }

    /**
     * @param list<int>   $promptTokens
     * @param list<float> $logits
     *
     * @return \Generator<int, int>
     */
    private function decodeLoop(array $promptTokens, array $logits, SamplingParams $params): \Generator
    {
        $session = $this->requireSession();
        $nPast = \count($promptTokens);
        $eos = $this->runtime->eosToken($session);

        for ($i = 0; $i < $params->maxTokens; ++$i) {
            $token = $this->sampler->sample($logits, $params);

            if ($token === $eos) {
                return;
            }

            yield $token;

            $logits = $this->runtime->evaluate($session, [$token], $nPast);
            ++$nPast;
        }
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
