<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\ModelHub;

use FerryAI\ModelHub\HuggingFaceClient;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests HuggingFace API with an auth token (for gated/private models).
 *
 * Requires a valid HuggingFace token set via the HF_TOKEN or HUGGINGFACE_TOKEN
 * environment variable. Skipped otherwise.
 */
#[Group('integration')]
#[CoversNothing]
final class HuggingFaceAuthIntegrationTest extends TestCase
{
    private ?string $token;

    protected function setUp(): void
    {
        $env = getenv('HF_TOKEN') ?: getenv('HUGGINGFACE_TOKEN');

        if ($env === false || $env === '') {
            self::markTestSkipped('No HF_TOKEN or HUGGINGFACE_TOKEN environment variable set.');
        }

        $this->token = $env;
    }

    public function testAuthenticatedClientListsFilesForGatedModel(): void
    {
        $client = new HuggingFaceClient($this->token);
        $files = $client->listFiles('meta-llama/Llama-3.2-1B');

        self::assertNotEmpty($files, 'Authenticated request should list files for a gated model');
    }

    public function testAuthenticatedClientReturnsModelInfo(): void
    {
        $client = new HuggingFaceClient($this->token);
        $info = $client->getModelInfo('meta-llama/Llama-3.2-1B');

        self::assertNotEmpty($info);
        self::assertArrayHasKey('modelId', $info);
    }

    public function testUnauthenticatedClientCannotAccessGatedModel(): void
    {
        $client = new HuggingFaceClient(null);
        $info = $client->getModelInfo('meta-llama/Llama-3.2-1B');

        self::assertSame([], $info);
    }
}
