<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Unit\Grammar;

use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Grammar\GbnfMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GbnfMatcher::class)]
final class GbnfMatcherTest extends TestCase
{
    private function matcher(string $gbnf): GbnfMatcher
    {
        return new GbnfMatcher(GbnfGrammar::fromString($gbnf));
    }

    public function testAnyCharAtomMatchesSingleCharacter(): void
    {
        $m = $this->matcher('root ::= "a" . "b"');

        self::assertTrue($m->isComplete('axb'));
        self::assertTrue($m->isComplete('a9b'));
        self::assertFalse($m->isComplete('ab'), '`.` must consume exactly one character');
        self::assertFalse($m->isComplete('axxb'));
    }

    public function testLiteralAlternation(): void
    {
        $m = $this->matcher('root ::= "yes" | "no"');

        self::assertTrue($m->isComplete('yes'));
        self::assertTrue($m->isComplete('no'));
        self::assertFalse($m->isComplete('y'));
        self::assertFalse($m->isComplete('yess'));
        self::assertFalse($m->isComplete('maybe'));
    }

    public function testLiteralViablePrefix(): void
    {
        $m = $this->matcher('root ::= "yes" | "no"');

        self::assertTrue($m->isViable(''));
        self::assertTrue($m->isViable('y'));
        self::assertTrue($m->isViable('ye'));
        self::assertTrue($m->isViable('yes'));
        self::assertTrue($m->isViable('n'));
        self::assertFalse($m->isViable('x'));
        self::assertFalse($m->isViable('yess'));
        self::assertFalse($m->isViable(' no'));
    }

    public function testCharClassAndPlus(): void
    {
        $m = $this->matcher('root ::= [a-z]+');

        self::assertTrue($m->isComplete('abc'));
        self::assertTrue($m->isViable('a'));
        self::assertTrue($m->isViable(''));
        self::assertFalse($m->isComplete(''));
        self::assertFalse($m->isComplete('ab1'));
        self::assertFalse($m->isViable('A'));
    }

    public function testCharClassNegationAndStar(): void
    {
        $m = $this->matcher('root ::= "a" [^0-9]* "z"');

        self::assertTrue($m->isComplete('az'));
        self::assertTrue($m->isComplete('abcz'));
        self::assertTrue($m->isViable('ab'));
        self::assertFalse($m->isComplete('a1z'));
    }

    public function testOptionalAndSequence(): void
    {
        $m = $this->matcher('root ::= "-"? [0-9]+');

        self::assertTrue($m->isComplete('42'));
        self::assertTrue($m->isComplete('-42'));
        self::assertTrue($m->isViable('-'));
        self::assertFalse($m->isComplete('--4'));
    }

    public function testRuleReferencesAndGrouping(): void
    {
        $m = $this->matcher(<<<'GBNF'
            root ::= greeting " " name
            greeting ::= "hi" | "hello"
            name ::= ("A" | "B")+
            GBNF);

        self::assertTrue($m->isComplete('hi A'));
        self::assertTrue($m->isComplete('hello AB'));
        self::assertTrue($m->isViable('hello '));
        self::assertFalse($m->isComplete('hey A'));
        self::assertFalse($m->isComplete('hi C'));
    }

    public function testWhitespaceAndCommentsInGrammar(): void
    {
        $m = $this->matcher(<<<'GBNF'
            # a boolean
            root   ::=   "true"   |   "false"   # trailing comment
            GBNF);

        self::assertTrue($m->isComplete('true'));
        self::assertTrue($m->isComplete('false'));
        self::assertTrue($m->isViable('tr'));
    }
}
