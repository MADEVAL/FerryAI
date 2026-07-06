<?php

declare(strict_types=1);

namespace FerryAI\Core\Tensor;

/**
 * Shared tensor helper methods used by ArrayTensor (tensor package) and
 * CpuNativeTensor (cpu-backend package). Extracted to eliminate ~200 lines
 * of duplicated code across both implementations.
 */
trait CommonTensorOps
{
    /**
     * @param array<mixed> $data
     *
     * @return int[]
     */
    private static function inferShape(array $data): array
    {
        $dims = [];
        $node = $data;

        while (\is_array($node)) {
            $dims[] = \count($node);
            $key = \array_key_first($node);
            $node = $key === null ? null : ($node[$key] ?? null);
        }

        return $dims;
    }

    /**
     * @param int[] $dims
     *
     * @return int[]
     */
    private static function strides(array $dims): array
    {
        $strides = [];
        $stride = 1;

        for ($i = \count($dims) - 1; $i >= 0; --$i) {
            $strides[$i] = $stride;
            $stride *= $dims[$i];
        }

        \ksort($strides);

        return $strides;
    }

    /**
     * @param int[] $dims
     *
     * @return int[]
     */
    private static function unravel(int $index, array $dims): array
    {
        $multi = [];

        for ($i = \count($dims) - 1; $i >= 0; --$i) {
            $multi[$i] = $index % $dims[$i];
            $index = \intdiv($index, $dims[$i]);
        }

        \ksort($multi);

        return $multi;
    }

    /**
     * @param array<int, mixed>               $data
     * @param array<int, int|array{int, int}> $slices
     */
    private static function applySlice(array $data, array $slices, int $axis): mixed
    {
        $spec = $slices[$axis] ?? null;

        if ($spec === null) {
            return \array_map(
                static fn(mixed $child): mixed => \is_array($child)
                    ? self::applySlice($child, $slices, $axis + 1)
                    : $child,
                $data,
            );
        }

        if (\is_int($spec)) {
            $selected = $data[$spec];

            return \is_array($selected) ? self::applySlice($selected, $slices, $axis + 1) : $selected;
        }

        [$start, $length] = $spec;
        $window = \array_slice($data, $start, $length);

        return \array_map(
            static fn(mixed $child): mixed => \is_array($child)
                ? self::applySlice($child, $slices, $axis + 1)
                : $child,
            $window,
        );
    }
}
