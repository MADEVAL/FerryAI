<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

class InferenceException extends FerryAIException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    #[\Override]
    public function errorCode(): string
    {
        return 'FERRY_AI_INFERENCE';
    }
}
