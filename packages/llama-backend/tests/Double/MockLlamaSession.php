<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Tests\Double;

use FerryAI\LlamaBackend\Runtime\LlamaSession;

/**
 * Deterministic session double. The cursor tracks the scripted generation position.
 */
final class MockLlamaSession implements LlamaSession
{
    public int $cursor = 0;

    public bool $released = false;
}
