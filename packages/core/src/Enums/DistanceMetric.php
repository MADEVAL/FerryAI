<?php

declare(strict_types=1);

namespace FerryAI\Core\Enums;

enum DistanceMetric: string
{
    case COSINE = 'cosine';
    case EUCLIDEAN = 'euclidean';
    case DOT = 'dot';
}
