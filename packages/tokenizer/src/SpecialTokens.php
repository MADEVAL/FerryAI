<?php

declare(strict_types=1);

namespace FerryAI\Tokenizer;

/**
 * Extracts role-keyed special tokens (bos/eos/unk/pad/cls/sep/mask) from a tokenizer config.
 */
final class SpecialTokens
{
    private const array CONTENT_TO_ROLE = [
        '<s>' => 'bos',
        '</s>' => 'eos',
        '<unk>' => 'unk',
        '<pad>' => 'pad',
        '<mask>' => 'mask',
        '[CLS]' => 'cls',
        '[SEP]' => 'sep',
        '[PAD]' => 'pad',
        '[UNK]' => 'unk',
        '[MASK]' => 'mask',
    ];

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, int>
     */
    public static function extract(array $config): array
    {
        $special = [];
        $added = \is_array($config['added_tokens'] ?? null) ? $config['added_tokens'] : [];

        foreach ($added as $token) {
            if (!\is_array($token)) {
                continue;
            }

            $content = $token['content'] ?? null;
            $id = $token['id'] ?? null;

            if (\is_string($content) && \is_int($id) && isset(self::CONTENT_TO_ROLE[$content])) {
                $special[self::CONTENT_TO_ROLE[$content]] = $id;
            }
        }

        return $special;
    }
}
