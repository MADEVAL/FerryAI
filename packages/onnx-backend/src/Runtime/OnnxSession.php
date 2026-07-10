<?php

declare(strict_types=1);

namespace FerryAI\OnnxBackend\Runtime;

/**
 * Opaque handle to a loaded ONNX inference session.
 *
 * Marker interface: concrete runtimes return their own implementation
 * (`NativeOnnxSession` for production, a test double for unit tests).
 */
interface OnnxSession {}
