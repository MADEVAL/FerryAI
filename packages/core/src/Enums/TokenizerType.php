<?php

declare(strict_types=1);

namespace FerryAI\Core\Enums;

enum TokenizerType: string
{
    case BPE = 'bpe';
    case WordPiece = 'wordpiece';
    case SentencePiece = 'sentencepiece';
    case Unigram = 'unigram';
}
