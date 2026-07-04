<?php

declare(strict_types=1);

namespace FerryAI\Core\Exception;

class ModelNotFoundException extends FerryAIException
{
    public function __construct(private readonly string $source)
    {
        parent::__construct(\sprintf(
            "Model '%s' was not found. Verify the path, URL or HuggingFace id is correct and accessible.",
            $source,
        ));
    }

    #[\Override]
    public function errorCode(): string
    {
        return 'FERRY_AI_MODEL_NOT_FOUND';
    }

    public function source(): string
    {
        return $this->source;
    }
}
