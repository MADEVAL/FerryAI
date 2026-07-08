<?php

declare(strict_types=1);

namespace FerryAI\Embedding;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Embedder as EmbedderContract;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Contracts\Tokenizer;
use FerryAI\Embedding\Pooling\ClsPooling;
use FerryAI\Embedding\Pooling\EosPooling;
use FerryAI\Embedding\Pooling\MaxPooling;
use FerryAI\Embedding\Pooling\MeanPooling;
use FerryAI\Embedding\Pooling\PoolingStrategy;

final class Embedder implements EmbedderContract
{
    private ?Model $model = null;

    private PoolingStrategy $poolingStrategy;

    private int $modelDimension = 0;

    private bool $modelLoaded = false;

    public function __construct(
        private string $modelName,
        private Backend $backend,
        private Tokenizer $tokenizer,
        ?string $pooling = null,
        private bool $normalize = true,
    ) {
        $pooling ??= EmbeddedModels::get($modelName)['pooling'] ?? 'mean';

        $this->poolingStrategy = match ($pooling) {
            'mean' => new MeanPooling(),
            'cls' => new ClsPooling(),
            'eos' => new EosPooling(),
            'max' => new MaxPooling(),
            default => new MeanPooling(),
        };
    }

    private function ensureModelLoaded(): void
    {
        if ($this->modelLoaded) {
            return;
        }

        $this->model = $this->backend->load($this->modelName);
        $outputs = $this->model->outputs();
        $firstOutput = \reset($outputs);
        $outputShape = $firstOutput['shape'] ?? [0];
        $this->modelDimension = $outputShape[\count($outputShape) - 1];
        $this->modelLoaded = true;
    }

    #[\Override]
    public function embed(string $text): array
    {
        $this->ensureModelLoaded();
        \assert($this->model !== null);

        $encoded = $this->tokenizer->encode($text);
        $seqLen = \count($encoded);
        $attentionMask = \array_fill(0, $seqLen, 1);

        $inputs = [
            'input_ids' => [$encoded],
            'attention_mask' => [$attentionMask],
        ];

        if (\array_key_exists('token_type_ids', $this->model->inputs())) {
            $inputs['token_type_ids'] = [\array_fill(0, $seqLen, 0)];
        }

        $outputs = $this->model->run($inputs);
        $hiddenStates = $outputs['token_embeddings'] ?? $outputs['last_hidden_state'] ?? \reset($outputs);

        if ($hiddenStates instanceof \FerryAI\Core\Contracts\Tensor) {
            $hiddenStates = $hiddenStates->toArray();
        }

        $hiddenStates = \is_array($hiddenStates) ? $hiddenStates : [];

        // Strip the batch dimension only when present: [batch, seq, hidden] -> [seq, hidden].
        // Models that emit 2D [seq, hidden] are left untouched so pooling still runs.
        if (isset($hiddenStates[0][0]) && \is_array($hiddenStates[0][0])) {
            $hiddenStates = $hiddenStates[0];
        }

        if (isset($hiddenStates[0]) && \is_array($hiddenStates[0])) {
            $vector = $this->poolingStrategy->pool($hiddenStates, [$attentionMask]);
        } elseif ($hiddenStates !== [] && \is_array($hiddenStates)) {
            $vector = $hiddenStates;
        } else {
            $vector = [];
        }

        if ($this->normalize) {
            return $this->normalize($vector);
        }

        return $vector;
    }

    #[\Override]
    public function embedBatch(array $texts): array
    {
        $results = [];

        foreach ($texts as $text) {
            $results[] = $this->embed($text);
        }

        return $results;
    }

    #[\Override]
    public function dimension(): int
    {
        $this->ensureModelLoaded();

        return $this->modelDimension;
    }

    #[\Override]
    public function normalize(array $vector): array
    {
        $sumOfSquares = 0.0;

        foreach ($vector as $value) {
            $sumOfSquares += $value * $value;
        }

        $norm = \sqrt($sumOfSquares);

        if ($norm === 0.0) {
            return $vector;
        }

        $result = [];

        foreach ($vector as $value) {
            $result[] = $value / $norm;
        }

        return $result;
    }

    #[\Override]
    public function cosineSimilarity(array $a, array $b): float
    {
        if (\count($a) !== \count($b)) {
            throw new \FerryAI\Core\Exception\ValidationException(\sprintf(
                'cosineSimilarity() requires vectors of equal length, got %d and %d.',
                \count($a),
                \count($b),
            ));
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($a as $i => $valueA) {
            $valueB = $b[$i];
            $dotProduct += $valueA * $valueB;
            $normA += $valueA * $valueA;
            $normB += $valueB * $valueB;
        }

        $normA = \sqrt($normA);
        $normB = \sqrt($normB);

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    #[\Override]
    public function modelName(): string
    {
        return $this->modelName;
    }
}
