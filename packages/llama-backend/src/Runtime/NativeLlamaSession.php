<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Runtime;

use FerryAI\LlamaBackend\FFI\LlamaContext;

/**
 * Native session handle wrapping a {@see LlamaContext}.
 *
 * Excluded from static analysis (FFI boundary).
 */
final class NativeLlamaSession implements LlamaSession
{
    public function __construct(private readonly LlamaContext $context) {}

    public function context(): LlamaContext
    {
        return $this->context;
    }
}
