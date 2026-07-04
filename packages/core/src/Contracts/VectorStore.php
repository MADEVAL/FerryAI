<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

interface VectorStore
{
    /**
     * Adds a single vector.
     *
     * @param float[]                   $vector   the vector (float32)
     * @param array<string, mixed>|null $metadata arbitrary JSON metadata
     *
     * @throws \FerryAI\Core\Exception\ShapeMismatchException when the dimension does not match the collection
     */
    public function add(string $id, array $vector, ?array $metadata = null): void;

    /**
     * Adds a batch of vectors.
     *
     * @param array<int, array{id: string, vector: float[], metadata?: array<string, mixed>}> $items
     */
    public function addBatch(array $items): void;

    /**
     * Searches for the k nearest neighbours.
     *
     * @param float[]                   $queryVector
     * @param array<string, mixed>|null $filter
     *
     * @return array<int, array{id: string, distance: float, metadata: array<string, mixed>}>
     */
    public function search(array $queryVector, int $k = 10, ?array $filter = null): array;

    /**
     * Deletes a vector by id.
     */
    public function delete(string $id): void;

    /**
     * Deletes vectors matching a metadata filter.
     *
     * @param array<string, mixed> $filter
     *
     * @return int number of deleted vectors
     */
    public function deleteByFilter(array $filter): int;

    /**
     * Updates the vector and/or metadata for an id.
     *
     * @param float[]|null              $vector
     * @param array<string, mixed>|null $metadata
     */
    public function update(string $id, ?array $vector = null, ?array $metadata = null): void;

    /**
     * Returns the number of vectors in the collection.
     */
    public function count(): int;

    /**
     * Returns the vector dimension of the collection.
     */
    public function dimension(): int;

    /**
     * Returns the collection name.
     */
    public function collectionName(): string;

    /**
     * Returns an iterator over all vectors in the collection.
     *
     * @return \Iterator<int, array{id: string, vector: float[], metadata: array<string, mixed>}>
     */
    public function iterator(): \Iterator;

    /**
     * Exports the whole collection.
     *
     * @return array<int, array{id: string, vector: float[], metadata: array<string, mixed>}>
     */
    public function export(): array;

    /**
     * Clears the collection entirely.
     */
    public function clear(): void;
}
