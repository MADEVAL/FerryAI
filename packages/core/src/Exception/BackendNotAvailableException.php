<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

class BackendNotAvailableException extends FerryAIException
{
    public function __construct(
        private readonly string $backendType,
        private readonly ?string $reason = null,
    ) {
        $message = \sprintf("Backend '%s' is not available", $backendType);

        if ($reason !== null) {
            $message .= \sprintf(': %s', $reason);
        }

        $message .= '. Verify the required native shared library is installed and compatible with this OS/arch.';

        parent::__construct($message);
    }

    #[\Override]
    public function errorCode(): string
    {
        return 'FERRY_AI_BACKEND_NOT_AVAILABLE';
    }

    public function backendType(): string
    {
        return $this->backendType;
    }

    public function reason(): ?string
    {
        return $this->reason;
    }
}
