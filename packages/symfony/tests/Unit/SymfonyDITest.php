<?php

declare(strict_types=1);

namespace FerryAI\Symfony\Tests\Unit;

use FerryAI\Symfony\DependencyInjection\Configuration;
use FerryAI\Symfony\DependencyInjection\FerryAIExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Configuration::class)]
#[CoversClass(FerryAIExtension::class)]
final class SymfonyDITest extends TestCase
{
    public function testConfigurationReturnsTree(): void
    {
        $config = new Configuration();

        $tree = $config->getConfigTree();

        self::assertIsArray($tree);
        self::assertArrayHasKey('ferry_ai', $tree);
        self::assertArrayHasKey('backend', $tree['ferry_ai']);
    }

    public function testExtensionLoadsConfig(): void
    {
        $extension = new FerryAIExtension();

        $extension->load([['backend' => 'llama', 'device' => 'cpu']]);

        $config = $extension->getConfig();
        self::assertSame('llama', $config['backend']);
        self::assertSame('cpu', $config['device']);
    }

    public function testExtensionUsesDefaults(): void
    {
        $extension = new FerryAIExtension();

        $extension->load([]);

        $config = $extension->getConfig();
        self::assertSame('auto', $config['backend']);
    }
}
