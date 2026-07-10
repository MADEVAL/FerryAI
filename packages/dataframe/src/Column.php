<?php

declare(strict_types=1);

namespace FerryAI\DataFrame;

final readonly class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public array $data,
    ) {}

    public function count(): int
    {
        return \count($this->data);
    }

    public static function inferType(array $data): string
    {
        if ($data === []) {
            return 'string';
        }

        $allInt = \count(\array_filter($data, '\is_int')) === \count($data);

        if ($allInt) {
            return 'int';
        }

        $allNumeric = true;

        foreach ($data as $value) {
            if (!\is_int($value) && !\is_float($value)) {
                $allNumeric = false;
                break;
            }
        }

        if ($allNumeric) {
            return 'float';
        }

        $uniqueCount = \count(\array_unique($data));
        $totalCount = \count($data);

        if ($totalCount > 0 && $uniqueCount / $totalCount <= 0.2) {
            return 'categorical';
        }

        return 'string';
    }
}
