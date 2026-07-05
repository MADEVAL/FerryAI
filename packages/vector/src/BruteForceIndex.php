<?php

declare(strict_types=1);

namespace FerryAI\Vector;

final class BruteForceIndex
{
    /**
     * Search for k nearest neighbours using brute force.
     *
     * @param array<int, float>                                                                         $queryVector
     * @param array<int, array{id: string, vector: array<int, float>, metadata?: array<string, mixed>}> $vectors
     * @param int                                                                                       $k
     * @param string                                                                                    $metric      'cosine' | 'euclidean' | 'dot'
     *
     * @return array<int, array{id: string, distance: float}>
     */
    public function search(array $queryVector, array $vectors, int $k, string $metric = 'cosine'): array
    {
        if ($vectors === []) {
            return [];
        }

        $heap = new class ($k) {
            /** @var array<int, array{id: string, distance: float}> */
            private array $items = [];
            private int $size = 0;

            public function __construct(private int $capacity) {}

            public function push(string $id, float $distance): void
            {
                if ($this->size < $this->capacity) {
                    $this->items[] = ['id' => $id, 'distance' => $distance];
                    $this->size++;
                } elseif ($distance < $this->items[$this->size - 1]['distance']) {
                    $this->items[$this->size - 1] = ['id' => $id, 'distance' => $distance];
                } else {
                    return;
                }

                \usort($this->items, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);
            }

            /** @return array<int, array{id: string, distance: float}> */
            public function toArray(): array
            {
                return $this->items;
            }
        };

        $distanceFn = $this->distanceFunction($metric);

        foreach ($vectors as $item) {
            $distance = $distanceFn($queryVector, $item['vector']);
            $heap->push($item['id'], $distance);
        }

        return $heap->toArray();
    }

    /**
     * @return callable(array<int, float>, array<int, float>): float
     */
    private function distanceFunction(string $metric): callable
    {
        return match ($metric) {
            'euclidean' => $this->euclideanDistance(...),
            'dot' => $this->dotProduct(...),
            default => $this->cosineDistance(...),
        };
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    private function cosineDistance(array $a, array $b): float
    {
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
            return 1.0;
        }

        return 1.0 - ($dotProduct / ($normA * $normB));
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    private function euclideanDistance(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $valueA) {
            $diff = $valueA - $b[$i];
            $sum += $diff * $diff;
        }

        return \sqrt($sum);
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     */
    private function dotProduct(array $a, array $b): float
    {
        $sum = 0.0;

        foreach ($a as $i => $valueA) {
            $sum += $valueA * $b[$i];
        }

        return -$sum;
    }
}
