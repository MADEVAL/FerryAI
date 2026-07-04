<?php

declare(strict_types=1);

namespace FerryAI\Core\Enums;

enum GraphOptimizationLevel: string
{
    case DISABLE_ALL = 'disable_all';
    case BASIC = 'basic';
    case EXTENDED = 'extended';
    case ALL = 'all';
}
