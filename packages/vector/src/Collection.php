<?php

declare(strict_types=1);

namespace FerryAI\Vector;

use FerryAI\Core\Contracts\VectorStore;
use FerryAI\Core\ValueObjects\Shape;

final class Collection implements VectorStore
{
    private BruteForceIndex $bruteForce;

    private MetadataFilter $filter;

    private SqliteVecExtension $vecExtension;

    private bool $useVec = false;

    public function __construct(
        private string $name,
        private int $dimensionValue,
        private SQLiteStore $store,
        private string $metric = 'cosine',
    ) {
        $this->bruteForce = new BruteForceIndex();
        $this->filter = new MetadataFilter();
        $this->vecExtension = new SqliteVecExtension();
        $this->initVecIndex();
    }

    private function initVecIndex(): void
    {
        // sqlite-vec (vec0) only supports L2 and cosine; 'dot' must fall back to brute force
        // so it is not silently computed as cosine.
        if ($this->dimensionValue < 1 || !\in_array($this->metric, ['cosine', 'euclidean'], true)) {
            return;
        }

        if (!$this->vecExtension->load($this->store)) {
            return;
        }

        $this->useVec = true;
        $this->vecExtension->createIndex($this->store, $this->name, $this->dimensionValue, $this->metric);

        foreach ($this->store->iterateVectors($this->name) as $row) {
            $this->vecExtension->upsert($this->store, $this->name, $row['id'], $this->unpackVector($row['vector']));
        }
    }

    #[\Override]
    public function add(string $id, array $vector, ?array $metadata = null): void
    {
        $this->validateDimension($vector);
        $blob = $this->packVector($vector);
        $metadataJson = $metadata !== null ? \json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;
        $this->store->insertVector($this->name, $id, $blob, \is_string($metadataJson) ? $metadataJson : null);

        if ($this->useVec) {
            $this->vecExtension->upsert($this->store, $this->name, $id, $vector);
        }
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
        if ($this->useVec && $filter === null) {
            return $this->vecSearch($queryVector, $k);
        }

        $vectors = $this->loadAllVectors();

        if ($vectors === []) {
            return [];
        }

        if ($filter !== null) {
            $filtered = \array_filter($vectors, fn(array $item): bool => $this->filter->matches(
                $item['metadata'] ?? [],
                $filter,
            ));
            $vectors = \array_values($filtered);
        }

        $results = $this->bruteForce->search($queryVector, $vectors, $k, $this->metric);

        $output = [];

        foreach ($results as $result) {
            foreach ($vectors as $v) {
                if ($v['id'] === $result['id']) {
                    $output[] = [
                        'id' => $result['id'],
                        'distance' => $result['distance'],
                        'metadata' => $v['metadata'] ?? [],
                    ];
                    break;
                }
            }
        }

        return $output;
    }

    /**
     * @param  array<int, float>                                                              $queryVector
     * @return array<int, array{id: string, distance: float, metadata: array<string, mixed>}>
     */
    private function vecSearch(array $queryVector, int $k): array
    {
        $output = [];

        foreach ($this->vecExtension->search($this->store, $this->name, $queryVector, $k) as $hit) {
            $row = $this->store->getVector($this->name, $hit['id']);
            $metadata = $row !== null && $row['metadata'] !== null ? \json_decode($row['metadata'], true) : [];

            $output[] = [
                'id' => $hit['id'],
                'distance' => $hit['distance'],
                'metadata' => \is_array($metadata) ? $metadata : [],
            ];
        }

        return $output;
    }

    #[\Override]
    public function delete(string $id): void
    {
        $this->store->deleteVector($this->name, $id);

        if ($this->useVec) {
            $this->vecExtension->remove($this->store, $this->name, $id);
        }
    }

    #[\Override]
    public function deleteByFilter(array $filter): int
    {
        $vectors = $this->loadAllVectors();
        $deleted = 0;

        foreach ($vectors as $item) {
            if ($this->filter->matches($item['metadata'] ?? [], $filter)) {
                $this->store->deleteVector($this->name, $item['id']);

                if ($this->useVec) {
                    $this->vecExtension->remove($this->store, $this->name, $item['id']);
                }

                $deleted++;
            }
        }

        return $deleted;
    }

    #[\Override]
    public function update(string $id, ?array $vector = null, ?array $metadata = null): void
    {
        $existing = ($vector === null || $metadata === null)
            ? $this->store->getVector($this->name, $id)
            : null;

        if ($vector !== null) {
            $this->validateDimension($vector);
            $blob = $this->packVector($vector);
        } else {
            $blob = $existing['vector'] ?? '';
        }

        if ($metadata !== null) {
            $metadataJson = \json_encode($metadata, JSON_UNESCAPED_UNICODE);
        } else {
            $metadataJson = $existing['metadata'] ?? null;
        }

        $this->store->insertVector($this->name, $id, $blob, \is_string($metadataJson) ? $metadataJson : null);

        if ($this->useVec && $blob !== '') {
            $this->vecExtension->upsert($this->store, $this->name, $id, $this->unpackVector($blob));
        }
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
            $vector = $this->unpackVector($row['vector']);
            $metadata = $row['metadata'] !== null ? \json_decode($row['metadata'], true) : [];

            yield [
                'id' => $row['id'],
                'vector' => $vector,
                'metadata' => $metadata,
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

        if ($this->useVec) {
            $this->vecExtension->clear($this->store, $this->name);
        }
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

    /**
     * @param array<int, float> $vector
     */
    private function packVector(array $vector): string
    {
        return \pack('f*', ...$vector);
    }

    /**
     * @return array<int, float>
     */
    private function unpackVector(string $blob): array
    {
        $data = \unpack('f*', $blob);

        if ($data === false) {
            return [];
        }

        return \array_values($data);
    }

    /**
     * @return array<int, array{id: string, vector: array<int, float>, metadata: array<string, mixed>}>
     */
    private function loadAllVectors(): array
    {
        $vectors = [];

        foreach ($this->store->iterateVectors($this->name) as $row) {
            $vectors[] = [
                'id' => $row['id'],
                'vector' => $this->unpackVector($row['vector']),
                'metadata' => $row['metadata'] !== null ? \json_decode($row['metadata'], true) : [],
            ];
        }

        return $vectors;
    }
}
