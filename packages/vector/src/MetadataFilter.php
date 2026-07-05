<?php

declare(strict_types=1);

namespace FerryAI\Vector;

final class MetadataFilter
{
    /**
     * Check if metadata matches the filter.
     *
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $filter
     */
    public function matches(array $metadata, array $filter): bool
    {
        return $this->evaluate($metadata, $filter);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $filter
     */
    private function evaluate(array $metadata, array $filter): bool
    {
        if (isset($filter['and'])) {
            foreach ($filter['and'] as $condition) {
                /** @var array<string, mixed> $condition */
                if (!$this->evaluate($metadata, $condition)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($filter['or'])) {
            foreach ($filter['or'] as $condition) {
                /** @var array<string, mixed> $condition */
                if ($this->evaluate($metadata, $condition)) {
                    return true;
                }
            }

            return false;
        }

        if (isset($filter['not'])) {
            /** @var array<string, mixed> $notFilter */
            $notFilter = $filter['not'];

            return !$this->evaluate($metadata, $notFilter);
        }

        foreach ($filter as $key => $condition) {
            if (!\is_array($condition)) {
                if (($metadata[$key] ?? null) !== $condition) {
                    return false;
                }

                continue;
            }

            $exists = \array_key_exists($key, $metadata);
            $value = $metadata[$key] ?? null;

            foreach ($condition as $operator => $operand) {
                if ($operator === 'exists') {
                    if ((bool) $operand !== $exists) {
                        return false;
                    }
                } elseif (!$this->applyOperator($value, (string) $operator, $operand)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function applyOperator(mixed $value, string $operator, mixed $operand): bool
    {
        return match ($operator) {
            'eq' => $value === $operand,
            'neq' => $value !== $operand,
            'gt' => \is_numeric($value) && $value > $operand,
            'gte' => \is_numeric($value) && $value >= $operand,
            'lt' => \is_numeric($value) && $value < $operand,
            'lte' => \is_numeric($value) && $value <= $operand,
            'in' => \is_array($operand) && \in_array($value, $operand, true),
            'nin' => \is_array($operand) && !\in_array($value, $operand, true),
            'contains' => \is_string($value) && \str_contains($value, (string) $operand),
            default => false,
        };
    }

    /**
     * Convert filter to a PHP predicate closure.
     *
     * @param array<string, mixed> $filter
     */
    public function toPhp(array $filter): \Closure
    {
        return fn(array $metadata): bool => $this->matches($metadata, $filter);
    }
}
