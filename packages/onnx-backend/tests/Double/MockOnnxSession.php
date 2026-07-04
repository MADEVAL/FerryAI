<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Tests\Double;

use FerryAI\OnnxBackend\Runtime\OnnxSession;

/**
 * Deterministic session double for unit tests.
 */
final class MockOnnxSession implements OnnxSession
{
    /**
     * @param array<string, array{name: string, shape: int[], dtype: string}>       $inputs
     * @param array<string, array{name: string, shape: int[], dtype: string}>       $outputs
     * @param array<string, array{data: array<mixed>, shape: int[], dtype: string}> $outputData
     */
    public function __construct(
        public readonly array $inputs = [],
        public readonly array $outputs = [],
        public readonly array $outputData = [],
    ) {}
}
