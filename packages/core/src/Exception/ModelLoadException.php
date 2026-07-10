<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

class ModelLoadException extends FerryAIException
{
    public function __construct(
        private readonly string $path,
        private readonly string $reason,
    ) {
        parent::__construct(\sprintf(
            "Failed to load model '%s': %s. Verify the file is a valid, uncorrupted model of a supported format.",
            $path,
            $reason,
        ));
    }

    #[\Override]
    public function errorCode(): string
    {
        return 'FERRY_AI_MODEL_LOAD';
    }

    public function path(): string
    {
        return $this->path;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
