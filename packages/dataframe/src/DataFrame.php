<?php

declare(strict_types=1);

namespace FerryAI\DataFrame;

use FerryAI\Core\Contracts\DataFrame as DataFrameContract;
use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\ValueObjects\Shape;
use FerryAI\Tensor\ArrayTensor;

final class DataFrame implements DataFrameContract
{
    /** @var array<string, Column> */
    private array $columns = [];

    /** @var string[] */
    private array $columnOrder = [];

    private int $rowCount = 0;

    private int $iteratorPosition = 0;

    public function __construct(Column ...$columns)
    {
        $counts = [];

        foreach ($columns as $column) {
            $this->columns[$column->name] = $column;
            $this->columnOrder[] = $column->name;
            $counts[] = $column->count();
        }

        if ($counts !== []) {
            $uniqueCounts = \array_unique($counts);

            if (\count($uniqueCounts) !== 1) {
                throw new ValidationException('All columns must have the same number of rows.');
            }

            $this->rowCount = $counts[0];
        }
    }

    #[\Override]
    public function columns(): array
    {
        return $this->columnOrder;
    }

    /**
     * @return string[]
     */
    #[\Override]
    public function dtypes(): array
    {
        $types = [];

        foreach ($this->columnOrder as $name) {
            $types[$name] = $this->columns[$name]->type;
        }

        return $types;
    }

    #[\Override]
    public function numRows(): int
    {
        return $this->rowCount;
    }

    #[\Override]
    public function numCols(): int
    {
        return \count($this->columns);
    }

    #[\Override]
    public function filter(callable $predicate): DataFrameContract
    {
        $indices = [];

        for ($i = 0; $i < $this->rowCount; ++$i) {
            $row = $this->buildRow($i);

            if ($predicate($row)) {
                $indices[] = $i;
            }
        }

        return $this->slice($indices);
    }

    #[\Override]
    public function sort(string $column, bool $ascending = true): DataFrameContract
    {
        if (!isset($this->columns[$column])) {
            throw new ValidationException("Column '{$column}' not found.");
        }

        $indices = \range(0, $this->rowCount - 1);
        $colData = $this->columns[$column]->data;

        \usort($indices, static function (int $a, int $b) use ($colData, $ascending): int {
            $left = $colData[$a];
            $right = $colData[$b];

            if ($left < $right) {
                return $ascending ? -1 : 1;
            }

            if ($left > $right) {
                return $ascending ? 1 : -1;
            }

            return 0;
        });

        return $this->slice($indices);
    }

    /**
     * @return array<string, DataFrameContract>
     */
    #[\Override]
    public function groupBy(string $column): array
    {
        if (!isset($this->columns[$column])) {
            throw new ValidationException("Column '{$column}' not found.");
        }

        $colData = $this->columns[$column]->data;
        $buckets = [];

        for ($i = 0; $i < $this->rowCount; ++$i) {
            $key = (string) $colData[$i];

            if (!isset($buckets[$key])) {
                $buckets[$key] = [];
            }

            $buckets[$key][] = $i;
        }

        $result = [];

        foreach ($buckets as $key => $indices) {
            $result[$key] = $this->slice($indices);
        }

        return $result;
    }

    #[\Override]
    public function aggregate(string $column, string $function): float|int
    {
        if (!isset($this->columns[$column])) {
            throw new ValidationException("Column '{$column}' not found.");
        }

        $data = $this->columns[$column]->data;

        if ($data === []) {
            throw new ValidationException('Cannot aggregate empty DataFrame.');
        }

        return match ($function) {
            'sum' => \array_sum($data),
            'mean' => \array_sum($data) / \count($data),
            'min' => \min($data),
            'max' => \max($data),
            'count' => \count($data),
            default => throw new ValidationException("Unknown aggregate function: '{$function}'. Use sum, mean, min, max, count."),
        };
    }

    /**
     * @param string[] $columns
     */
    #[\Override]
    public function select(array $columns): DataFrameContract
    {
        $selectedColumns = [];

        foreach ($columns as $name) {
            if (!isset($this->columns[$name])) {
                throw new ValidationException("Column '{$name}' not found.");
            }

            $selectedColumns[] = $this->columns[$name];
        }

        return new self(...$selectedColumns);
    }

    /**
     * @return mixed[]
     */
    #[\Override]
    public function column(string $name): array
    {
        if (!isset($this->columns[$name])) {
            throw new ValidationException("Column '{$name}' not found.");
        }

        return $this->columns[$name]->data;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function row(int $index): array
    {
        if ($index < 0 || $index >= $this->rowCount) {
            throw new ValidationException("Row index {$index} out of bounds. Must be 0.." . ($this->rowCount - 1) . '.');
        }

        return $this->buildRow($index);
    }

    #[\Override]
    public function toTensor(string $column): Tensor
    {
        if (!isset($this->columns[$column])) {
            throw new ValidationException("Column '{$column}' not found.");
        }

        $col = $this->columns[$column];
        $data = $col->data;

        if ($col->type === 'int') {
            return new ArrayTensor(\array_values($data), new Shape([\count($data)]), DType::Int32);
        }

        if ($col->type === 'float') {
            return new ArrayTensor(\array_values($data), new Shape([\count($data)]), DType::Float32);
        }

        $unique = \array_values(\array_unique($data));
        $labelMap = \array_flip($unique);
        $encoded = \array_map(static fn(mixed $value): int => $labelMap[$value], $data);

        return new ArrayTensor(\array_values($encoded), new Shape([\count($encoded)]), DType::Int32);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @param string[]|null                    $columns
     */
    #[\Override]
    public static function fromArray(array $data, ?array $columns = null): DataFrameContract
    {
        if ($data === []) {
            return new self();
        }

        $colNames = $columns ?? \array_keys($data[0]);
        $pivoted = \array_fill_keys($colNames, []);

        foreach ($data as $row) {
            foreach ($colNames as $name) {
                $pivoted[$name][] = $row[$name] ?? null;
            }
        }

        $columnObjects = [];

        foreach ($colNames as $name) {
            $columnData = $pivoted[$name];
            $type = Column::inferType($columnData);
            $columnObjects[] = new Column($name, $type, $columnData);
        }

        return new self(...$columnObjects);
    }

    #[\Override]
    public static function fromCsv(string $path, bool $hasHeader = true): DataFrameContract
    {
        $reader = new IO\CsvReader();

        return $reader->read($path, $hasHeader);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[\Override]
    public function toArray(): array
    {
        $result = [];

        for ($i = 0; $i < $this->rowCount; ++$i) {
            $result[] = $this->buildRow($i);
        }

        return $result;
    }

    #[\Override]
    public function toCsv(string $path, bool $includeHeader = true): void
    {
        $writer = new IO\CsvWriter();
        $writer->write($this, $path, $includeHeader);
    }

    public function current(): mixed
    {
        return $this->buildRow($this->iteratorPosition);
    }

    public function key(): mixed
    {
        return $this->iteratorPosition;
    }

    public function next(): void
    {
        ++$this->iteratorPosition;
    }

    public function rewind(): void
    {
        $this->iteratorPosition = 0;
    }

    public function valid(): bool
    {
        return $this->iteratorPosition < $this->rowCount;
    }

    public function count(): int
    {
        return $this->rowCount;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRow(int $index): array
    {
        $row = [];

        foreach ($this->columnOrder as $name) {
            $row[$name] = $this->columns[$name]->data[$index];
        }

        return $row;
    }

    /**
     * @param int[] $indices
     */
    private function slice(array $indices): self
    {
        $columns = [];

        foreach ($this->columnOrder as $name) {
            $col = $this->columns[$name];
            $slicedData = \array_map(static fn(int $idx): mixed => $col->data[$idx], $indices);
            $columns[] = new Column($col->name, $col->type, \array_values($slicedData));
        }

        return new self(...$columns);
    }
}
