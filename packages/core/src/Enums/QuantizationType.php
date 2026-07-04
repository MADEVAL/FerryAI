<?php

declare(strict_types=1);

namespace FerryAI\Core\Enums;

enum QuantizationType: string
{
    case FLOAT32 = 'float32';
    case FLOAT16 = 'float16';
    case INT8 = 'int8';
    case BINARY = 'binary';
}
