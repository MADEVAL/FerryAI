<?php

declare(strict_types=1);

namespace FerryAI\Core\Contracts;

/**
 * @extends \Iterator<int, array<string, mixed>>
 */
interface DataFrame extends \Iterator, \Countable
{
    /**
     * @return string[] column names
     */
    public function columns(): array;

    /**
     * @return string[] column types (float, int, string, categorical)
     */
    public function dtypes(): array;

    /**
     * Returns the number of rows.
     */
    public function numRows(): int;

    /**
     * Returns the number of columns.
     */
    public function numCols(): int;

    /**
     * Filters rows by a predicate.
     *
     * @param callable(array<string, mixed>): bool $predicate
     */
    public function filter(callable $predicate): self;

    /**
     * Sorts by a column.
     */
    public function sort(string $column, bool $ascending = true): self;

    /**
     * Groups by a column.
     *
     * @return array<string, static>
     */
    public function groupBy(string $column): array;

    /**
     * Aggregates a column.
     *
     * @param string $function sum, mean, min, max, count
     */
    public function aggregate(string $column, string $function): float|int;

    /**
     * Selects a subset of columns.
     *
     * @param string[] $columns
     */
    public function select(array $columns): self;

    /**
     * Returns a column as an array.
     *
     * @return mixed[]
     */
    public function column(string $name): array;

    /**
     * Returns a row as an associative array.
     *
     * @return array<string, mixed>
     */
    public function row(int $index): array;

    /**
     * Converts a column to a tensor.
     */
    public function toTensor(string $column): Tensor;

    /**
     * Imports from CSV.
     */
    public static function fromCsv(string $path, bool $hasHeader = true): self;

    /**
     * Imports from an array.
     *
     * @param array<int, array<string, mixed>> $data
     * @param string[]|null                    $columns
     */
    public static function fromArray(array $data, ?array $columns = null): self;

    /**
     * Exports to an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array;

    /**
     * Exports to CSV.
     */
    public function toCsv(string $path, bool $includeHeader = true): void;
}
