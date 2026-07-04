<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

use FerryAI\Core\ValueObjects\Shape;

class ShapeMismatchException extends FerryAIException
{
    public function __construct(
        private readonly Shape $expected,
        private readonly Shape $actual,
    ) {
        parent::__construct(\sprintf(
            'Shape mismatch: expected [%s] but got [%s]. Verify the input tensor matches the model signature.',
            $expected,
            $actual,
        ));
    }

    #[\Override]
    public function errorCode(): string
    {
        return 'FERRY_AI_SHAPE_MISMATCH';
    }

    public function expected(): Shape
    {
        return $this->expected;
    }

    public function actual(): Shape
    {
        return $this->actual;
    }
}
