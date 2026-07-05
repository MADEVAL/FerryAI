<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend;

use FerryAI\Core\ValueObjects\ChatMessage;

/**
 * Formats ChatML-style messages into a prompt string for a specific model family.
 */
final class ChatFormatter
{
    public function __construct(private readonly string $format = 'chatml') {}

    /**
     * Detects the prompt format from a model name.
     */
    public static function detectFormat(string $modelName): string
    {
        $name = strtolower($modelName);

        return match (true) {
            str_contains($name, 'llama-3') || str_contains($name, 'llama3') => 'llama3',
            str_contains($name, 'mistral') || str_contains($name, 'mixtral') => 'mistral',
            str_contains($name, 'gemma') => 'gemma',
            str_contains($name, 'phi') => 'phi',
            default => 'chatml',
        };
    }

    /**
     * @param array<int, ChatMessage|array{role?: string, content?: mixed}> $messages
     */
    public function format(array $messages): string
    {
        $normalized = array_map($this->normalize(...), $messages);

        return match ($this->format) {
            'llama3' => $this->formatLlama3($normalized),
            'mistral' => $this->formatMistral($normalized),
            'gemma' => $this->formatGemma($normalized),
            'phi' => $this->formatPhi($normalized),
            default => $this->formatChatml($normalized),
        };
    }

    /**
     * @param ChatMessage|array{role?: string, content?: mixed} $message
     *
     * @return array{role: string, content: string}
     */
    private function normalize(ChatMessage|array $message): array
    {
        if ($message instanceof ChatMessage) {
            return ['role' => $message->role, 'content' => $this->stringifyContent($message->content)];
        }

        $roleRaw = $message['role'] ?? null;
        $role = \is_string($roleRaw) ? $roleRaw : 'user';

        return ['role' => $role, 'content' => $this->stringifyContent($message['content'] ?? '')];
    }

    /**
     * @param array<int|string, mixed>|string $content
     */
    private function stringifyContent(array|string $content): string
    {
        if (\is_string($content)) {
            return $content;
        }

        return json_encode($content, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    private function formatChatml(array $messages): string
    {
        $prompt = '';

        foreach ($messages as $message) {
            $prompt .= '<|im_start|>' . $message['role'] . "\n" . $message['content'] . "<|im_end|>\n";
        }

        return $prompt . "<|im_start|>assistant\n";
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    private function formatLlama3(array $messages): string
    {
        $prompt = '<|begin_of_text|>';

        foreach ($messages as $message) {
            $prompt .= '<|start_header_id|>' . $message['role'] . '<|end_header_id|>' . "\n\n"
                . $message['content'] . '<|eot_id|>';
        }

        return $prompt . '<|start_header_id|>assistant<|end_header_id|>' . "\n\n";
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    private function formatMistral(array $messages): string
    {
        $prompt = '<s>';
        $system = '';

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system = $message['content'] . "\n\n";

                continue;
            }

            if ($message['role'] === 'assistant') {
                $prompt .= ' ' . $message['content'] . '</s>';

                continue;
            }

            $prompt .= '[INST] ' . $system . $message['content'] . ' [/INST]';
            $system = '';
        }

        return $prompt;
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    private function formatGemma(array $messages): string
    {
        $prompt = '';

        foreach ($messages as $message) {
            $role = $message['role'] === 'assistant' ? 'model' : $message['role'];
            $prompt .= '<start_of_turn>' . $role . "\n" . $message['content'] . "<end_of_turn>\n";
        }

        return $prompt . "<start_of_turn>model\n";
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     */
    private function formatPhi(array $messages): string
    {
        $prompt = '';

        foreach ($messages as $message) {
            $prompt .= '<|' . $message['role'] . '|>' . "\n" . $message['content'] . "<|end|>\n";
        }

        return $prompt . "<|assistant|>\n";
    }
}
