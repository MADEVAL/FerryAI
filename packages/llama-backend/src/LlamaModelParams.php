<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend;

readonly class LlamaModelParams
{
    public function __construct(
        public int $nGpuLayers = 0,
        public bool $useMmap = true,
        public bool $useMlock = false,
        public bool $vocabOnly = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'n_gpu_layers' => $this->nGpuLayers,
            'use_mmap' => $this->useMmap,
            'use_mlock' => $this->useMlock,
            'vocab_only' => $this->vocabOnly,
        ];
    }
}
