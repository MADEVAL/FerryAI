<?php

declare(strict_types=1);

namespace FerryAI\Tests\Verification;

use FerryAI\AI;
use FerryAI\BackendRegistry;
use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Model;
use FerryAI\Core\Enums\BackendType;
use FerryAI\Core\Enums\Device;
use FerryAI\CpuBackend\CpuNativeModel;
use FerryAI\ModelPool;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Runtime regression guard: AI::warmup() must pre-load models under the SAME pool key that
 * AI::loadPooled() looks up, so a warmed model is actually reused (loaded once) rather than
 * re-loaded cold on the first request while the warmed copy leaks pool memory.
 */
#[CoversNothing]
final class WarmupPoolKeyTest extends TestCase
{
    protected function setUp(): void
    {
        AI::reset();
    }

    protected function tearDown(): void
    {
        AI::reset();
    }

    public function testWarmedModelIsReusedByPooledLoad(): void
    {
        AI::config(['backend' => 'onnx']);

        $backend = new class implements Backend {
            public int $loads = 0;

            public function availableDevices(): array
            {
                return [Device::CPU];
            }

            public function load(string $source, ?Device $device = null): Model
            {
                $this->loads++;

                return new CpuNativeModel($source, []);
            }

            public function version(): string
            {
                return 'fake';
            }

            public function isAvailable(): bool
            {
                return true;
            }
        };

        $registry = (new \ReflectionProperty(AI::class, 'registry'))->getValue();
        self::assertInstanceOf(BackendRegistry::class, $registry);
        $registry->register(BackendType::Onnx, $backend);

        $path = '/models/fake.onnx';

        AI::warmup([$path]);

        self::assertSame(1, $backend->loads, 'warmup must load the model exactly once.');

        $pooled = (new \ReflectionMethod(AI::class, 'loadPooled'))->invoke(null, $backend, $path, null);

        self::assertInstanceOf(CpuNativeModel::class, $pooled);
        self::assertSame(1, $backend->loads, 'loadPooled must reuse the warmed model, not reload it.');

        $pool = (new \ReflectionProperty(AI::class, 'modelPool'))->getValue();
        self::assertInstanceOf(ModelPool::class, $pool);
        self::assertSame(1, $pool->size(), 'Only one pooled entry — no duplicate under a mismatched key.');
    }
}
