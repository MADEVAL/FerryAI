<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

class InvalidStateException extends FerryAIException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }

    #[\Override]
    public function errorCode(): string
    {
        return 'FERRY_AI_INVALID_STATE';
    }
}
