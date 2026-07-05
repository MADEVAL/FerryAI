<?php

declare(strict_types=1);

namespace FerryAI\Core\ValueObjects;

use FerryAI\Core\Exception\ValidationException;

readonly class ChatMessage implements \JsonSerializable
{
    private const array VALID_ROLES = ['system', 'user', 'assistant', 'tool'];

    /**
     * @param string              $role       system | user | assistant | tool
     * @param string|array<mixed> $content    text or an array of content parts (multimodal)
     * @param string|null         $name       participant name (optional)
     * @param string|null         $toolCallId tool call id (for role=tool)
     * @param array<mixed>|null   $toolCalls  tool calls (for role=assistant)
     *
     * @throws ValidationException when the role is not supported
     */
    public function __construct(
        public string $role,
        public string|array $content,
        public ?string $name = null,
        public ?string $toolCallId = null,
        public ?array $toolCalls = null,
    ) {
        if (!\in_array($role, self::VALID_ROLES, true)) {
            throw new ValidationException(\sprintf(
                "Invalid chat role '%s'. Expected one of: %s.",
                $role,
                implode(', ', self::VALID_ROLES),
            ));
        }
    }

    public static function system(string $content): self
    {
        return new self('system', $content);
    }

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    /**
     * Creates a message from an OpenAI-compatible associative array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var string|array<mixed> $content */
        $content = $data['content'] ?? '';

        return new self(
            role: (string) ($data['role'] ?? ''),
            content: $content,
            name: isset($data['name']) ? (string) $data['name'] : null,
            toolCallId: isset($data['tool_call_id']) ? (string) $data['tool_call_id'] : null,
            toolCalls: isset($data['tool_calls']) && \is_array($data['tool_calls']) ? $data['tool_calls'] : null,
        );
    }

    /**
     * Exports to an OpenAI-compatible associative array; null fields are omitted.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'role' => $this->role,
            'content' => $this->content,
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        if ($this->toolCalls !== null) {
            $data['tool_calls'] = $this->toolCalls;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
