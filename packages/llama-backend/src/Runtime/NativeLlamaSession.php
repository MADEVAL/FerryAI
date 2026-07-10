<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Runtime;

use FerryAI\LlamaBackend\FFI\FerryLlama;

/**
 * Native session handle: the model + context CData plus cached vocab/eos.
 *
 * Excluded from static analysis (FFI boundary).
 */
final class NativeLlamaSession implements LlamaSession
{
    public function __construct(
        public readonly FerryLlama $ffi,
        public readonly \FFI\CData $model,
        public readonly \FFI\CData $context,
        public readonly int $nVocab,
        public readonly int $nCtx,
        public readonly int $eosToken,
    ) {}
}
