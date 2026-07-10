<?php

declare(strict_types=1);

namespace FerryAI\Core\Enums;

enum IndexType: string
{
    case HNSW = 'hnsw';
    case IVF = 'ivf';
    case FLAT = 'flat';
}
