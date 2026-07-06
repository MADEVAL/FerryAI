<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Sampling;

use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Sampling\GrammarSampler;
use FerryAI\LlamaBackend\Sampling\GreedySampler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrammarSampler::class)]
final class GrammarSamplerTest extends TestCase
{
    public function testDelegatesSamplingWithoutDecoder(): void
    {
        $grammar = GbnfGrammar::fromString('root ::= "a"');
        $sampler = new GrammarSampler($grammar, new GreedySampler());

        self::assertSame(2, $sampler->sample([0.1, 0.2, 0.9], new SamplingParams()));
    }

    public function testExposesGrammar(): void
    {
        $grammar = GbnfGrammar::fromString('root ::= "a"');

        self::assertSame($grammar, (new GrammarSampler($grammar))->grammar());
    }

    public function testBoundSamplerRejectsOffGrammarTokens(): void
    {
        // token 0 = EOS, 5 = "yes", 6 = "no", 7 = "maybe"
        $decoder = static fn(int $t): string => [5 => 'yes', 6 => 'no', 7 => 'maybe'][$t] ?? '';
        $sampler = (new GrammarSampler(GbnfGrammar::fromString('root ::= "yes" | "no"')))->bind($decoder, 0);

        // "maybe" has the highest logit but is off-grammar → the sampler must pick "yes".
        $logits = [0 => -5.0, 5 => 1.0, 6 => 0.5, 7 => 9.0];

        self::assertSame(5, $sampler->sample($logits, new SamplingParams()));
    }

    public function testBoundSamplerEmitsEosWhenComplete(): void
    {
        $decoder = static fn(int $t): string => [5 => 'yes', 6 => 'no'][$t] ?? '';
        $sampler = (new GrammarSampler(GbnfGrammar::fromString('root ::= "yes" | "no"')))->bind($decoder, 0);

        // consume "yes"
        self::assertSame(5, $sampler->sample([5 => 1.0, 6 => 0.5, 7 => 9.0], new SamplingParams()));

        // grammar complete: EOS (token 0) is now allowed and wins even over off-grammar "no".
        self::assertSame(0, $sampler->sample([0 => 5.0, 6 => 9.0], new SamplingParams()));
    }

    public function testCharPrefilterRejectsTokensWithWrongFirstByte(): void
    {
        // Token 5="yes", 6="no", and noise tokens whose first char is never 'y' or 'n'.
        $noiseIds = [];
        $decoderMap = [5 => 'yes', 6 => 'no'];

        for ($i = 100; $i < 200; $i++) {
            $c = chr($i % 24 + 97); // a-x only, never y or n
            $decoderMap[$i] = $c . 'xxx';
            $noiseIds[] = $i;
        }

        $decoder = static fn(int $t): string => $decoderMap[$t] ?? '';
        $sampler = (new GrammarSampler(GbnfGrammar::fromString('root ::= "yes" | "no"')))->bind($decoder, 0);

        $logits = [0 => -5.0, 5 => 1.0, 6 => 0.5] + array_fill_keys($noiseIds, 9.0);

        self::assertSame(5, $sampler->sample($logits, new SamplingParams()));
    }

    public function testBindStartsFreshState(): void
    {
        $decoder = static fn(int $t): string => [5 => 'yes', 6 => 'no'][$t] ?? '';
        $base = new GrammarSampler(GbnfGrammar::fromString('root ::= "yes" | "no"'));

        $a = $base->bind($decoder, 0);
        $a->sample([5 => 9.0], new SamplingParams()); // a now accumulated "yes"

        $b = $base->bind($decoder, 0); // fresh state
        self::assertSame(6, $b->sample([6 => 9.0, 5 => 1.0], new SamplingParams()));
    }
}
