<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\ValueObjects;

use FerryAI\Core\Exception\ValidationException;
use FerryAI\Core\ValueObjects\ChatMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChatMessage::class)]
final class ChatMessageTest extends TestCase
{
    public function testUserFactory(): void
    {
        self::assertSame('user', ChatMessage::user('Hello')->role);
    }

    public function testSystemFactory(): void
    {
        self::assertSame('system', ChatMessage::system('You are helpful')->role);
    }

    public function testAssistantFactory(): void
    {
        self::assertSame('assistant', ChatMessage::assistant('Sure')->role);
    }

    public function testInvalidRoleIsRejected(): void
    {
        $this->expectException(ValidationException::class);

        new ChatMessage('invalid', 'x');
    }

    public function testFromArrayEqualsFactory(): void
    {
        self::assertEquals(
            ChatMessage::user('Hi'),
            ChatMessage::fromArray(['role' => 'user', 'content' => 'Hi']),
        );
    }

    public function testToArrayOmitsNullFields(): void
    {
        self::assertSame(['role' => 'user', 'content' => 'Hi'], ChatMessage::user('Hi')->toArray());
    }

    public function testJsonSerializeDelegatesToToArray(): void
    {
        $message = ChatMessage::user('Hi');

        self::assertSame($message->toArray(), $message->jsonSerialize());
    }
}
