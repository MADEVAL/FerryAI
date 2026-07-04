<?php

declare(strict_types=1);

namespace FerryAI\Core\Tests\Unit\Exception;

use FerryAI\Core\Exception\ConfigurationException;
use FerryAI\Core\Exception\FerryAIException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigurationException::class)]
final class ConfigurationExceptionTest extends TestCase
{
    public function testExtendsFerryAIException(): void
    {
        self::assertInstanceOf(FerryAIException::class, new ConfigurationException('device', 'unknown value'));
    }

    public function testExposesConfigKey(): void
    {
        self::assertSame('device', (new ConfigurationException('device', 'unknown value'))->configKey());
    }

    public function testErrorCode(): void
    {
        self::assertSame(
            'FERRY_AI_CONFIGURATION',
            (new ConfigurationException('device', 'unknown value'))->errorCode(),
        );
    }

    public function testMessageMentionsKeyAndReason(): void
    {
        $message = (new ConfigurationException('device', 'unknown value'))->getMessage();

        self::assertStringContainsString('device', $message);
        self::assertStringContainsString('unknown value', $message);
    }
}
