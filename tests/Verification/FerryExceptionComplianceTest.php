<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Exception\BackendNotAvailableException;
use FerryAI\Core\Exception\FerryAIException;
use FerryAI\Core\Exception\InvalidStateException;
use FerryAI\CpuBackend\RubixMLAdapter;
use FerryAI\LlamaBackend\Runtime\LlamaSession;
use FerryAI\LlamaBackend\Runtime\NativeLlamaRuntime;
use FerryAI\OnnxBackend\Runtime\NativeOnnxRuntime;
use FerryAI\OnnxBackend\Runtime\OnnxSession;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard for AGENTS.md §5: every thrown exception must extend FerryAIException
 * (so it carries a FERRY_AI_* errorCode and cannot escape catch (FerryAIException)). These sites
 * previously threw native \RuntimeException / \InvalidArgumentException.
 */
#[CoversNothing]
final class FerryExceptionComplianceTest extends TestCase
{
    public function testRubixMLAdapterLoadModelThrowsFerryException(): void
    {
        if (\interface_exists('Rubix\ML\Estimator')) {
            self::markTestSkipped('RubixML is installed; the not-installed path cannot be exercised.');
        }

        $this->assertFerry(BackendNotAvailableException::class, static fn() => (new RubixMLAdapter())->loadModel('x'));
    }

    public function testRubixMLAdapterPredictThrowsFerryException(): void
    {
        if (\interface_exists('Rubix\ML\Estimator')) {
            self::markTestSkipped('RubixML is installed.');
        }

        $this->assertFerry(BackendNotAvailableException::class, static fn() => (new RubixMLAdapter())->predict(null, [[1.0]]));
    }

    public function testRubixMLAdapterProbaThrowsFerryException(): void
    {
        if (\interface_exists('Rubix\ML\Estimator')) {
            self::markTestSkipped('RubixML is installed.');
        }

        $this->assertFerry(BackendNotAvailableException::class, static fn() => (new RubixMLAdapter())->proba(null, [[1.0]]));
    }

    public function testNativeLlamaRuntimeRejectsForeignSessionWithFerryException(): void
    {
        $foreign = new class implements LlamaSession {};

        $this->assertFerry(
            InvalidStateException::class,
            static fn() => (new NativeLlamaRuntime())->nVocab($foreign),
        );
    }

    public function testNativeOnnxRuntimeRejectsForeignSessionWithFerryException(): void
    {
        $foreign = new class implements OnnxSession {};

        $this->assertFerry(
            InvalidStateException::class,
            static fn() => (new NativeOnnxRuntime())->sessionInputs($foreign),
        );
    }

    /**
     * @param class-string<\Throwable> $expected
     */
    private function assertFerry(string $expected, callable $fn): void
    {
        try {
            $fn();
            self::fail('Expected ' . $expected . ' to be thrown.');
        } catch (\Throwable $e) {
            self::assertInstanceOf($expected, $e);
            self::assertInstanceOf(FerryAIException::class, $e);
        }
    }
}
