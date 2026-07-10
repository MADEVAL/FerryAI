<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\ValueObjects\ChatMessage;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\LlamaBackend\ChatFormatter;
use FerryAI\LlamaBackend\LlamaModel;
use FerryAI\LlamaBackend\Runtime\LlamaRuntimeInterface;
use FerryAI\LlamaBackend\Sampling\GreedySampler;
use FerryAI\LlamaBackend\Tests\Double\MockLlamaRuntime;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: the LlamaRuntimeInterface no longer declares parameters the native
 * layer silently drops. tokenize() must not expose $special, and evaluate()/evaluateTopK() must
 * not expose $nPast (the native context tracks KV position itself). End-to-end generation through
 * the mock runtime must still produce the scripted output after the signature cleanup.
 */
#[CoversNothing]
final class RuntimeInterfaceSignatureTest extends TestCase
{
    public function testTokenizeHasNoSpecialParameter(): void
    {
        $params = $this->paramNames('tokenize');

        self::assertNotContains('special', $params);
        self::assertSame(['session', 'text', 'addBos'], $params);
    }

    public function testEvaluateHasNoNPastParameter(): void
    {
        $params = $this->paramNames('evaluate');

        self::assertNotContains('nPast', $params);
        self::assertSame(['session', 'tokens'], $params);
    }

    public function testEvaluateTopKHasNoNPastParameter(): void
    {
        $params = $this->paramNames('evaluateTopK');

        self::assertNotContains('nPast', $params);
        self::assertSame(['session', 'tokens', 'k'], $params);
    }

    public function testGenerationStillWorksAfterSignatureCleanup(): void
    {
        $runtime = new MockLlamaRuntime(
            eos: 2,
            scripted: [10, 11],
            pieces: [10 => 'Hello', 11 => ' world'],
            promptTokens: [1, 5],
        );
        $session = $runtime->createSession(
            'model.gguf',
            new \FerryAI\LlamaBackend\LlamaModelParams(),
            new \FerryAI\LlamaBackend\LlamaContextParams(),
        );
        $model = new LlamaModel(
            $session,
            $runtime,
            new ChatFormatter('chatml'),
            new GreedySampler(),
            new ModelMetadata('llama-test', '1.0', '', '', [], 100),
            Device::CPU,
        );

        self::assertSame('Hello world', $model->runComplete([ChatMessage::user('Hi')])->text);
    }

    /**
     * @return list<string>
     */
    private function paramNames(string $method): array
    {
        return array_map(
            static fn(\ReflectionParameter $p): string => $p->getName(),
            (new \ReflectionMethod(LlamaRuntimeInterface::class, $method))->getParameters(),
        );
    }
}
