<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit;

use FerryAI\Core\ValueObjects\ChatMessage;
use FerryAI\LlamaBackend\ChatFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatFormatter::class)]
final class ChatFormatterTest extends TestCase
{
    public function testDetectFormat(): void
    {
        self::assertSame('llama3', ChatFormatter::detectFormat('meta-llama/Meta-Llama-3-8B-Instruct'));
        self::assertSame('mistral', ChatFormatter::detectFormat('mistralai/Mistral-7B-Instruct'));
        self::assertSame('gemma', ChatFormatter::detectFormat('google/gemma-2b-it'));
        self::assertSame('phi', ChatFormatter::detectFormat('microsoft/phi-2'));
        self::assertSame('chatml', ChatFormatter::detectFormat('some/unknown-model'));
    }

    public function testDetectFormatDoesNotMatchPhiInsideDolphin(): void
    {
        self::assertSame('chatml', ChatFormatter::detectFormat('cognitivecomputations/dolphin-2.6'));
        self::assertSame('phi', ChatFormatter::detectFormat('Phi-3-mini-4k-instruct'));
        self::assertSame('phi', ChatFormatter::detectFormat('phi2'));
    }

    public function testChatmlFormat(): void
    {
        $prompt = (new ChatFormatter('chatml'))->format([ChatMessage::user('Hello')]);

        self::assertStringContainsString('<|im_start|>user', $prompt);
        self::assertStringContainsString('Hello', $prompt);
        self::assertStringContainsString('<|im_start|>assistant', $prompt);
    }

    public function testLlama3Format(): void
    {
        $prompt = (new ChatFormatter('llama3'))->format([
            ChatMessage::system('You are helpful'),
            ChatMessage::user('Hi'),
        ]);

        self::assertStringContainsString('<|begin_of_text|>', $prompt);
        self::assertStringContainsString('<|start_header_id|>system<|end_header_id|>', $prompt);
    }

    public function testMistralFormat(): void
    {
        $prompt = (new ChatFormatter('mistral'))->format([ChatMessage::user('Hello')]);

        self::assertStringContainsString('[INST]', $prompt);
    }

    public function testAcceptsArrayMessages(): void
    {
        $prompt = (new ChatFormatter('chatml'))->format([['role' => 'user', 'content' => 'Hey']]);

        self::assertStringContainsString('Hey', $prompt);
    }

    public function testGemmaMapsToolRoleToUser(): void
    {
        $prompt = (new ChatFormatter('gemma'))->format([['role' => 'tool', 'content' => 'weather=sunny']]);

        self::assertStringContainsString('weather=sunny', $prompt);
        self::assertStringContainsString('<start_of_turn>user', $prompt);
        self::assertStringNotContainsString('<start_of_turn>tool', $prompt);
    }

    public function testPhiMapsToolRoleToUser(): void
    {
        $prompt = (new ChatFormatter('phi'))->format([['role' => 'tool', 'content' => 'weather=sunny']]);

        self::assertStringContainsString('weather=sunny', $prompt);
        self::assertStringNotContainsString('<|tool|>', $prompt);
    }
}
