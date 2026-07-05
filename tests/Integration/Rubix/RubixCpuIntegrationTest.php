<?php

declare(strict_types=1);

namespace FerryAI\Tests\Integration\Rubix;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real RubixML CPU backend end to end.
 *
 * RubixML ships amphp/amp ^1 whose files-autoload collides with the dev
 * toolchain's amphp (psalm), so it cannot be loaded into the main process.
 * The real run therefore happens in an isolated subprocess ({@see rubix_harness.php}),
 * and this test asserts on its JSON result. Point FERRY_AI_RUBIXML_AUTOLOAD at the
 * isolated rubix/ml vendor/autoload.php; skipped when unavailable.
 */
#[Group('integration')]
#[CoversNothing]
final class RubixCpuIntegrationTest extends TestCase
{
    private string $harness = '';

    protected function setUp(): void
    {
        if (getenv('FERRY_AI_SKIP_NATIVE') === '1') {
            self::markTestSkipped('Native tests skipped via FERRY_AI_SKIP_NATIVE=1.');
        }

        $this->harness = __DIR__ . '/rubix_harness.php';

        $autoload = getenv('FERRY_AI_RUBIXML_AUTOLOAD')
            ?: 'C:\\Users\\MASTER\\AppData\\Local\\Temp\\opencode\\rubixml\\vendor\\autoload.php';

        if (!\is_file($autoload)) {
            self::markTestSkipped('rubix/ml autoloader not found: ' . $autoload);
        }
    }

    public function testRealRubixCpuBackendEndToEnd(): void
    {
        $raw = \shell_exec(\escapeshellarg(\PHP_BINARY) . ' ' . \escapeshellarg($this->harness));

        self::assertIsString($raw);

        $data = \json_decode(\trim($raw), true);
        self::assertIsArray($data, 'Harness output was not JSON: ' . $raw);

        if (isset($data['skip'])) {
            self::markTestSkipped((string) $data['skip']);
        }

        self::assertTrue($data['available']);
        self::assertSame(['a', 'b'], $data['output']);
        self::assertGreaterThan(0.5, $data['proba_a']);
    }
}
