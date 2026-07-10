<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Grammar;

/**
 * A parsed GBNF expression node. A single typed class (rather than tagged arrays) keeps the
 * {@see GbnfMatcher} recogniser fully type-safe under static analysis.
 */
final class GbnfNode
{
    public const int LITERAL = 0;
    public const int CHAR_CLASS = 1;
    public const int REFERENCE = 2;
    public const int SEQUENCE = 3;
    public const int ALTERNATION = 4;
    public const int REPETITION = 5;

    /**
     * @param GbnfNode[]                  $children literal: none; ref: none; seq/alt: items; rep: single child
     * @param array<int, array{int, int}> $ranges   char-class codepoint ranges [lo, hi]
     */
    public function __construct(
        public int $kind,
        public string $text = '',
        public array $children = [],
        public array $ranges = [],
        public bool $negated = false,
        public int $min = 0,
        public ?int $max = null,
    ) {}
}
