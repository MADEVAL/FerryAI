<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

interface Embedder
{
    /**
     * Computes the embedding for a single text.
     *
     * @return float[] the embedding vector (float32)
     */
    public function embed(string $text): array;

    /**
     * Computes embeddings for a batch of texts.
     *
     * @param string[] $texts
     *
     * @return float[][]
     */
    public function embedBatch(array $texts): array;

    /**
     * Returns the embedding vector dimension.
     */
    public function dimension(): int;

    /**
     * Normalizes a vector (L2 norm).
     *
     * @param float[] $vector
     *
     * @return float[]
     */
    public function normalize(array $vector): array;

    /**
     * Computes the cosine similarity between two vectors.
     *
     * @param float[] $a
     * @param float[] $b
     */
    public function cosineSimilarity(array $a, array $b): float;

    /**
     * Returns the embedding model name.
     */
    public function modelName(): string;
}
