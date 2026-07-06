<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit;

use FerryAI\Core\Enums\Device;
use FerryAI\Core\Exception\InferenceException;
use FerryAI\Core\ValueObjects\ChatMessage;
use FerryAI\Core\ValueObjects\GenerationResult;
use FerryAI\Core\ValueObjects\ModelMetadata;
use FerryAI\LlamaBackend\ChatFormatter;
use FerryAI\LlamaBackend\LlamaModel;
use FerryAI\LlamaBackend\Sampling\GreedySampler;
use FerryAI\LlamaBackend\Tests\Double\MockLlamaRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LlamaModel::class)]
final class LlamaModelTest extends TestCase
{
    private function model(?MockLlamaRuntime $runtime = null): LlamaModel
    {
        $runtime ??= new MockLlamaRuntime(
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

        return new LlamaModel(
            $session,
            $runtime,
            new ChatFormatter('chatml'),
            new GreedySampler(),
            new ModelMetadata('llama-test', '1.0', '', '', [], 100),
            Device::CPU,
        );
    }

    public function testRunCompleteGeneratesText(): void
    {
        $result = $this->model()->runComplete([ChatMessage::user('Hi')]);

        self::assertInstanceOf(GenerationResult::class, $result);
        self::assertSame('Hello world', $result->text);
        self::assertSame(2, $result->tokensGenerated);
        self::assertSame(2, $result->tokensPrompt);
        self::assertSame(4, $result->tokensTotal);
    }

    public function testRunStreamYieldsPieces(): void
    {
        $pieces = iterator_to_array($this->model()->runStream([ChatMessage::user('Hi')]), false);

        self::assertSame(['Hello', ' world'], $pieces);
    }

    public function testRunGeneratesSingleToken(): void
    {
        $result = $this->model()->run([ChatMessage::user('Hi')]);

        self::assertSame('Hello', $result['text']);
        self::assertSame(10, $result['token']);
    }

    public function testInputsAndOutputs(): void
    {
        $model = $this->model();

        self::assertArrayHasKey('messages', $model->inputs());
        self::assertArrayHasKey('text', $model->outputs());
    }

    public function testRunAfterUnloadThrows(): void
    {
        $model = $this->model();
        $model->unload();

        $this->expectException(InferenceException::class);

        $model->runComplete([ChatMessage::user('Hi')]);
    }

    public function testMessagesViaMessagesKey(): void
    {
        $result = $this->model()->runComplete(['messages' => [ChatMessage::user('Hi')]]);

        self::assertSame('Hello world', $result->text);
    }

    public function testDeviceAndMetadata(): void
    {
        $model = $this->model();

        self::assertSame(Device::CPU, $model->device());
        self::assertSame('llama-test', $model->metadata()->name);
    }

    public function testFactoryGreedyWhenNoSamplerAndTemperatureZero(): void
    {
        $result = $this->modelWithoutSampler()->runComplete(
            [ChatMessage::user('Hi')],
            new \FerryAI\Core\ValueObjects\SamplingParams(temperature: 0.0),
        );

        self::assertSame('Hello world', $result->text);
    }

    public function testFactoryTopPWhenTemperaturePositive(): void
    {
        $result = $this->modelWithoutSampler()->runComplete(
            [ChatMessage::user('Hi')],
            new \FerryAI\Core\ValueObjects\SamplingParams(temperature: 0.7, seed: 42),
        );

        // The scripted token dominates the logits, so nucleus sampling still yields it.
        self::assertSame('Hello world', $result->text);
    }

    public function testRepetitionPenaltyChangesGreedySelection(): void
    {
        // Two candidate tokens every step; token 10 dominates. Without a penalty greedy repeats 10.
        $runtime = new MockLlamaRuntime(
            eos: 2,
            pieces: [10 => 'A', 11 => 'B'],
            promptTokens: [1, 5],
            fixedTopK: [10 => 10.0, 11 => 9.0],
        );
        $session = $runtime->createSession('m.gguf', new \FerryAI\LlamaBackend\LlamaModelParams(), new \FerryAI\LlamaBackend\LlamaContextParams());
        $model = new LlamaModel($session, $runtime, new ChatFormatter('chatml'), new GreedySampler());

        $noPenalty = $model->runComplete(
            [ChatMessage::user('Hi')],
            new \FerryAI\Core\ValueObjects\SamplingParams(temperature: 0.0, maxTokens: 2),
        );
        self::assertSame('AA', $noPenalty->text, 'without penalty greedy repeats the top token');

        $withPenalty = $model->runComplete(
            [ChatMessage::user('Hi')],
            new \FerryAI\Core\ValueObjects\SamplingParams(temperature: 0.0, maxTokens: 2, repetitionPenalty: 5.0),
        );
        self::assertSame('AB', $withPenalty->text, 'repetition penalty must steer away from the repeated token');
    }

    private function modelWithoutSampler(): LlamaModel
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

        return new LlamaModel($session, $runtime, new ChatFormatter('chatml'));
    }
}
