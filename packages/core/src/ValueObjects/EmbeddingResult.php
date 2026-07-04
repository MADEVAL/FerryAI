<?php

declare(strict_types=1);

namespace FerryAI\Core\ValueObjects;

readonly class EmbeddingResult
{
    /**
     * @param float[] $vector    the embedding vector
     * @param int     $dimension the vector dimension (must equal count($vector))
     * @param string  $modelName the model that produced the embedding
     *
     * @throws \InvalidArgumentException when $dimension does not match the vector length
     */
    public function __construct(
        public array $vector,
        public int $dimension,
        public string $modelName,
    ) {
        if ($dimension !== \count($vector)) {
            throw new \InvalidArgumentException(\sprintf(
                'dimension (%d) must equal the vector length (%d).',
                $dimension,
                \count($vector),
            ));
        }
    }
}
