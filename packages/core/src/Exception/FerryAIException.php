<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

class FerryAIException extends \RuntimeException
{
    public function errorCode(): string
    {
        return 'FERRY_AI_ERROR';
    }
}
