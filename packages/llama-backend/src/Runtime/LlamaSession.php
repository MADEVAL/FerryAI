<?php

declare(strict_types=1);

namespace FerryAI\LlamaBackend\Runtime;

/**
 * Opaque handle to a loaded llama.cpp model + context.
 *
 * Marker interface: the native runtime returns `NativeLlamaSession`, tests use a double.
 */
interface LlamaSession {}
