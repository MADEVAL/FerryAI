<?php

declare(strict_types=1);

namespace FerryAI\Embedding;

final class EmbeddedModels
{
    private const MODELS = [
        'all-MiniLM-L6-v2' => [
            'hf_id' => 'sentence-transformers/all-MiniLM-L6-v2',
            'dimension' => 384,
            'pooling' => 'mean',
        ],
        'all-mpnet-base-v2' => [
            'hf_id' => 'sentence-transformers/all-mpnet-base-v2',
            'dimension' => 768,
            'pooling' => 'mean',
        ],
        'multilingual-e5-small' => [
            'hf_id' => 'intfloat/multilingual-e5-small',
            'dimension' => 384,
            'pooling' => 'mean',
        ],
        'bge-small-en' => [
            'hf_id' => 'BAAI/bge-small-en-v1.5',
            'dimension' => 384,
            'pooling' => 'cls',
        ],
    ];

    /**
     * @return array<string, array{hf_id: string, dimension: int, pooling: string}>
     */
    public static function list(): array
    {
        return self::MODELS;
    }

    /**
     * @return array{hf_id: string, dimension: int, pooling: string}|null
     */
    public static function get(string $name): ?array
    {
        return self::MODELS[$name] ?? null;
    }

    public static function isEmbedded(string $name): bool
    {
        return isset(self::MODELS[$name]);
    }
}
