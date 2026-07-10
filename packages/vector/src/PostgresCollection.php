<?php

declare(strict_types=1);

namespace FerryAI\Vector;

use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\ValueObjects\Shape;

/**
 * {@see VectorStore} implementation backed by PostgreSQL + pgvector.
 *
 * Unlike {@see Collection} (which scans vectors with a PHP brute-force index),
 * this delegates nearest-neighbour ordering to the database via native pgvector
 * distance operators, optionally accelerated by an HNSW/IVFFlat index
 * (see {@see PostgresVecIndex}).
 *
 * Metadata filtering reuses the tested {@see MetadataFilter} semantics: matching
 * ids are resolved first, then the native distance query is restricted to them,
 * so ordering stays native while filter behaviour stays identical to SQLite.
 */
final class PostgresCollection implements VectorStore
{
    private MetadataFilter $filter;

    public function __construct(
        private string $name,
        private int $dimensionValue,
        private PostgresStore $store,
        private string $metric = 'cosine',
    ) {
        $this->filter = new MetadataFilter();
    }

    #[\Override]
    public function add(string $id, array $vector, ?array $metadata = null): void
    {
        $this->validateDimension($vector);
        $this->store->insertVector($this->name, $id, $vector, $metadata);
    }

    #[\Override]
    public function addBatch(array $items): void
    {
        foreach ($items as $item) {
            $this->add($item['id'], $item['vector'], $item['metadata'] ?? null);
        }
    }

    #[\Override]
    public function search(array $queryVector, int $k = 10, ?array $filter = null): array
    {
        $restrictIds = $filter !== null ? $this->matchingIds($filter) : null;

        return $this->store->search($this->name, $queryVector, $k, $this->metric, $restrictIds);
    }

    #[\Override]
    public function delete(string $id): void
    {
        $this->store->deleteVector($this->name, $id);
    }

    #[\Override]
    public function deleteByFilter(array $filter): int
    {
        $deleted = 0;

        foreach ($this->matchingIds($filter) as $id) {
            $this->store->deleteVector($this->name, $id);
            $deleted++;
        }

        return $deleted;
    }

    #[\Override]
    public function update(string $id, ?array $vector = null, ?array $metadata = null): void
    {
        $existing = $this->store->getVector($this->name, $id);

        if ($vector !== null) {
            $this->validateDimension($vector);
            $newVector = $vector;
        } else {
            $newVector = $existing['vector'] ?? [];
        }

        $newMetadata = $metadata ?? ($existing['metadata'] ?? null);

        $this->store->insertVector($this->name, $id, $newVector, $newMetadata);
    }

    #[\Override]
    public function count(): int
    {
        return $this->store->countVectors($this->name);
    }

    #[\Override]
    public function dimension(): int
    {
        return $this->dimensionValue;
    }

    #[\Override]
    public function collectionName(): string
    {
        return $this->name;
    }

    #[\Override]
    public function iterator(): \Iterator
    {
        foreach ($this->store->iterateVectors($this->name) as $row) {
            yield [
                'id' => $row['id'],
                'vector' => $row['vector'],
                'metadata' => $row['metadata'] ?? [],
            ];
        }
    }

    #[\Override]
    public function export(): array
    {
        $result = [];

        foreach ($this->iterator() as $item) {
            $result[] = $item;
        }

        return $result;
    }

    #[\Override]
    public function clear(): void
    {
        $this->store->clearCollection($this->name);
    }

    /**
     * @param  array<string, mixed> $filter
     * @return string[]
     */
    private function matchingIds(array $filter): array
    {
        $ids = [];

        foreach ($this->store->iterateVectors($this->name) as $row) {
            if ($this->filter->matches($row['metadata'] ?? [], $filter)) {
                $ids[] = $row['id'];
            }
        }

        return $ids;
    }

    /**
     * @param array<int, float> $vector
     */
    private function validateDimension(array $vector): void
    {
        if (\count($vector) !== $this->dimensionValue) {
            throw new \FerryAI\Core\Exception\ShapeMismatchException(
                new Shape([$this->dimensionValue]),
                new Shape([\count($vector)]),
            );
        }
    }
}
