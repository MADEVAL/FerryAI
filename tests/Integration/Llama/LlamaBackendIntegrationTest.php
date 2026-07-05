<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Llama;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Real llama.cpp chat through the AI facade + ferry_llama wrapper.
 *
 * Runs in an isolated subprocess ({@see llama_chat_harness.php}) — loading the
 * native DLL runs ggml global constructors that conflict with PHPUnit
 * (docs/DEBT_REPORT.md §12). Needs FERRY_AI_LLAMA_DIR (default D:\FerryAI) with
 * ferry_llama.dll + a .gguf model; skipped otherwise.
 */
#[Group('integration')]
#[CoversNothing]
final class LlamaBackendIntegrationTest extends TestCase
{
    private string $harness = '';

    protected function setUp(): void
    {
        if (getenv('FERRY_AI_SKIP_NATIVE') === '1') {
            self::markTestSkipped('Native tests skipped via FERRY_AI_SKIP_NATIVE=1.');
        }

        $this->harness = __DIR__ . '/llama_chat_harness.php';
        $dir = getenv('FERRY_AI_LLAMA_DIR') ?: 'D:\\FerryAI';

        if (!\is_file($dir . '\\ferry_llama.dll')) {
            self::markTestSkipped('ferry_llama.dll not found in ' . $dir . ' (build native/llama-wrapper).');
        }
    }

    private function chat(string $device, float $temperature = 0.0, string $mode = 'single'): array
    {
        $raw = \shell_exec(
            \escapeshellarg(\PHP_BINARY) . ' ' . \escapeshellarg($this->harness)
            . ' ' . $device . ' 16 ' . $temperature . ' ' . $mode
            . ' 2>' . (\PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'),
        );

        self::assertIsString($raw);
        $data = \json_decode(\trim($raw), true);
        self::assertIsArray($data, 'Harness output was not JSON: ' . $raw);

        if (isset($data['skip'])) {
            self::markTestSkipped((string) $data['skip']);
        }

        self::assertArrayNotHasKey('error', $data, 'Harness error: ' . ($data['error'] ?? ''));

        return $data;
    }

    public function testChatOnCpuGreedy(): void
    {
        $data = $this->chat('cpu', 0.0);

        self::assertGreaterThan(0, $data['tokens_prompt']);
        self::assertStringContainsStringIgnoringCase('paris', (string) $data['text']);
    }

    public function testChatOnGpu(): void
    {
        $data = $this->chat('cuda', 0.0);

        self::assertGreaterThan(0, $data['tokens_prompt']);
        self::assertNotSame('', (string) $data['text']);
    }

    public function testChatWithNucleusSampling(): void
    {
        // Non-zero temperature exercises the SamplerFactory top-p path end to end.
        $data = $this->chat('cpu', 0.7);

        self::assertNotSame('', (string) $data['text']);
    }

    public function testChatModelIsPooledAcrossCalls(): void
    {
        // Two chats in one process: the second must reuse the pooled model (no reload),
        // so it is markedly faster than the first (which pays the model load).
        $data = $this->chat('cuda', 0.0, 'twice');

        self::assertStringContainsStringIgnoringCase('paris', (string) $data['text1']);
        self::assertStringContainsStringIgnoringCase('paris', (string) $data['text2']);
        self::assertLessThan((int) $data['ms1'], (int) $data['ms2']);
    }
}
