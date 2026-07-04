<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

class ConfigurationException extends FerryAIException
{
    public function __construct(
        private readonly string $key,
        string $reason,
    ) {
        parent::__construct(\sprintf("Invalid configuration for '%s': %s.", $key, $reason));
    }

    #[\Override]
    public function errorCode(): string
    {
        return 'FERRY_AI_CONFIGURATION';
    }

    public function configKey(): string
    {
        return $this->key;
    }
}
