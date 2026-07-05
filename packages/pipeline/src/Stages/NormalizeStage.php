<?php

declare(strict_types=1);

namespace FerryAI\Pipeline\Stages;

use FerryAI\Core\Contracts\Stage;

final class NormalizeStage implements Stage
{
    #[\Override]
    public function process(mixed $input): mixed
    {
        if (!\is_array($input)) {
            return $input;
        }

        $sumOfSquares = 0.0;

        foreach ($input as $value) {
            $sumOfSquares += $value * $value;
        }

        $norm = \sqrt($sumOfSquares);

        if ($norm === 0.0) {
            return $input;
        }

        $result = [];

        foreach ($input as $value) {
            $result[] = $value / $norm;
        }

        return $result;
    }

    #[\Override]
    public function name(): string
    {
        return 'normalize';
    }
}
