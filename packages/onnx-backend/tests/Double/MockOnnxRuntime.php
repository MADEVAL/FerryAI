<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Tests\Double;

use FerryAI\Core\Enums\GraphOptimizationLevel;
use FerryAI\OnnxBackend\Runtime\OnnxRuntimeInterface;
use FerryAI\OnnxBackend\Runtime\OnnxSession;

/**
 * Deterministic {@see OnnxRuntimeInterface} double for unit tests.
 */
final class MockOnnxRuntime implements OnnxRuntimeInterface
{
    /** @var list<array{path: string, providers: list<string>, optimization: GraphOptimizationLevel}> */
    public array $createdSessions = [];

    /** @var list<array<string, array<mixed>>> */
    public array $runInputs = [];

    /**
     * @param list<string> $providers
     */
    public function __construct(
        private readonly bool $available = true,
        private readonly string $engineVersion = 'mock-1.0.0',
        private readonly array $providers = ['CPUExecutionProvider'],
        private readonly ?MockOnnxSession $session = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function version(): string
    {
        return $this->engineVersion;
    }

    /**
     * @return list<string>
     */
    public function availableProviders(): array
    {
        return $this->providers;
    }

    /**
     * @param list<string> $providerNames
     */
    public function createSession(
        string $path,
        array $providerNames,
        GraphOptimizationLevel $optimization = GraphOptimizationLevel::ALL,
    ): OnnxSession {
        $this->createdSessions[] = [
            'path' => $path,
            'providers' => $providerNames,
            'optimization' => $optimization,
        ];

        return $this->session ?? new MockOnnxSession();
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function sessionInputs(OnnxSession $session): array
    {
        return $this->mock($session)->inputs;
    }

    /**
     * @return array<string, array{name: string, shape: int[], dtype: string}>
     */
    public function sessionOutputs(OnnxSession $session): array
    {
        return $this->mock($session)->outputs;
    }

    /**
     * @param array<string, array<mixed>> $inputs
     *
     * @return array<string, array{data: array<mixed>, shape: int[], dtype: string}>
     */
    public function run(OnnxSession $session, array $inputs): array
    {
        $this->runInputs[] = $inputs;

        return $this->mock($session)->outputData;
    }

    private function mock(OnnxSession $session): MockOnnxSession
    {
        if (!$session instanceof MockOnnxSession) {
            throw new \InvalidArgumentException('MockOnnxRuntime requires a MockOnnxSession.');
        }

        return $session;
    }
}
