<?php

declare(strict_types=1);

namespace FerryAI\Core\Enums;

enum DType: string
{
    case Float32 = 'float32';
    case Float16 = 'float16';
    case Int32 = 'int32';
    case Int64 = 'int64';
    case String = 'string';

    /**
     * Размер одного элемента в байтах (String = 0 — переменная длина).
     */
    public function sizeInBytes(): int
    {
        return match ($this) {
            self::Float32 => 4,
            self::Float16 => 2,
            self::Int32 => 4,
            self::Int64 => 8,
            self::String => 0,
        };
    }
}
