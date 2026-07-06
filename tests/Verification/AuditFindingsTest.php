<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\AI;
use FerryAI\Core\Enums\BackendType;
use FerryAI\LlamaBackend\ChatFormatter;
use FerryAI\LlamaBackend\Grammar\GbnfGrammar;
use FerryAI\LlamaBackend\Grammar\GbnfMatcher;
use FerryAI\LlamaBackend\Grammar\JsonSchemaConverter;
use FerryAI\LlamaBackend\Sampling\TopPSampler;
use FerryAI\Core\ValueObjects\SamplingParams;
use FerryAI\Embedding\Pooling\EosPooling;
use FerryAI\ModelHub\Signature\Sha256Verifier;
use FerryAI\Tokenizer\PureBpeTokenizer;
use FerryAI\Vector\CollectionManager;
use FerryAI\Vector\SQLiteStore;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guards for the audit findings. Each test drives the real code path and
 * asserts the CORRECTED behaviour, so a regression is caught by execution, not source reading.
 */
#[CoversNothing]
final class AuditFindingsTest extends TestCase
{
    public function testBpeDecodeStripsFusedEndOfWordMarker(): void
    {
        $vocab = ['h' => 0, 'i' => 1, 'hi</w>' => 2];
        $merges = ['h i', 'hi </w>'];
        $tok = new PureBpeTokenizer($vocab, $merges);

        $decoded = $tok->decode($tok->encode('hi', false));

        self::assertStringNotContainsString('</w>', $decoded);
        self::assertSame('hi', $decoded);
    }

    public function testSeededTopPSamplerAdvancesRngAcrossTokens(): void
    {
        $logits = [0.1, 0.2, 0.3, 0.25, 0.15];
        $params = new SamplingParams(temperature: 1.0, topP: 0.99, seed: 42);
        $sampler = new TopPSampler();

        $picks = [];

        for ($i = 0; $i < 50; $i++) {
            $picks[] = $sampler->sample($logits, $params);
        }

        // Fixed: a persisted RNG advances, producing a diverse (non-degenerate) sequence.
        self::assertGreaterThan(1, \count(array_unique($picks)));
    }

    public function testGbnfMatcherMatchesAnyCharAtom(): void
    {
        $matcher = new GbnfMatcher(GbnfGrammar::fromString('root ::= "a" . "b"'));

        self::assertTrue($matcher->isComplete('axb'));
        self::assertFalse($matcher->isComplete('ab'));
    }

    public function testJsonSchemaConverterMakesPropertiesOptionalWhenRequiredAbsent(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'a' => ['type' => 'string'],
                'b' => ['type' => 'integer'],
            ],
        ];
        $matcher = new GbnfMatcher((new JsonSchemaConverter())->convert($schema));

        self::assertTrue($matcher->isComplete('{}'));
        self::assertTrue($matcher->isComplete('{"a":"x"}'));
        self::assertTrue($matcher->isComplete('{"b":7}'));
        self::assertTrue($matcher->isComplete('{"a":"x","b":7}'));
    }

    public function testSha256VerifierAcceptsStandardChecksumFileWithFilename(): void
    {
        $data = tempnam(sys_get_temp_dir(), 'ferry_data_');
        self::assertIsString($data);
        file_put_contents($data, 'hello world');
        $hash = hash_file('sha256', $data);

        $checksum = $data . '.sha256';
        file_put_contents($checksum, $hash . '  ' . basename($data) . "\n");

        self::assertTrue(Sha256Verifier::verifyFile($data, $checksum));

        @unlink($data);
        @unlink($checksum);
    }

    public function testChatFormatterDoesNotMisdetectDolphinAsPhi(): void
    {
        self::assertSame('chatml', ChatFormatter::detectFormat('dolphin-2.6'));
        self::assertSame('phi', ChatFormatter::detectFormat('microsoft/phi-3'));
    }

    public function testEosPoolingReturnsEmptyOnEmptyHiddenStates(): void
    {
        self::assertSame([], (new EosPooling())->pool([]));
    }

    public function testCollectionHonoursConfiguredEuclideanMetric(): void
    {
        $store = new SQLiteStore(':memory:');
        $store->createCollection('c', 3, 'euclidean');
        $collection = (new CollectionManager($store))->open('c');

        $collection->add('A', [2.0, 0.0, 0.0]);
        $collection->add('B', [0.9, 0.1, 0.0]);

        $results = $collection->search([1.0, 0.0, 0.0], 1);

        self::assertSame('B', $results[0]['id']);
    }

    public function testSqliteStoreRejectsUnsafeCollectionName(): void
    {
        $store = new SQLiteStore(':memory:');

        $this->expectException(\FerryAI\Core\Exception\ValidationException::class);
        $store->createCollection('a"b', 3);
    }

    public function testAiConfigHonoursConfiguredBackend(): void
    {
        AI::reset();
        AI::config(['backend' => 'llama']);

        self::assertSame(BackendType::Llama, AI::activeBackend());

        AI::reset();
    }
}
