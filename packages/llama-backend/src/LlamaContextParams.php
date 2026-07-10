<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend;

readonly class LlamaContextParams
{
    public function __construct(
        public int $nCtx = 2048,
        public int $nBatch = 512,
        public int $nGpuLayers = 0,
        public int $nThreads = 0,
        public bool $flashAttn = false,
        public bool $useMmap = true,
        public bool $useMlock = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'n_ctx' => $this->nCtx,
            'n_batch' => $this->nBatch,
            'n_gpu_layers' => $this->nGpuLayers,
            'n_threads' => $this->nThreads,
            'flash_attn' => $this->flashAttn,
            'use_mmap' => $this->useMmap,
            'use_mlock' => $this->useMlock,
        ];
    }
}
