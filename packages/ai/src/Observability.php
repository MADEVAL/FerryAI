<?php

declare(strict_types=1);

namespace FerryAI;

use FerryAI\Core\AIConfig;
use FerryAI\Core\Logger;

/**
 * Cross-cutting instrumentation for the {@see AI} facade.
 *
 * Wraps a high-level operation (embed/chat/classify/...) with optional metrics,
 * profiling and logging. Every channel is opt-in and off by default, so the
 * common path (`measure()` with all channels disabled) adds no measurable
 * overhead and never touches the filesystem — important for test runs.
 *
 * Instrumentation lives here, in the `ai` package, rather than inside the
 * isolated backends: backends must not depend on `ai` (backend isolation +
 * dependency graph), so cross-cutting concerns are applied at the facade layer.
 */
final class Observability
{
    private ?Logger $logger = null;

    public function __construct(
        private bool $metrics = false,
        private bool $profiling = false,
        private bool $logging = false,
        ?string $logFile = null,
    ) {
        if ($this->logging) {
            $this->logger = new Logger($logFile);
        }
    }

    public static function fromConfig(AIConfig $config): self
    {
        $logFile = $config->get('observability.log_file');

        return new self(
            metrics: (bool) $config->get('observability.metrics', false),
            profiling: (bool) $config->get('observability.profiling', false),
            logging: (bool) $config->get('observability.logging', false),
            logFile: \is_string($logFile) ? $logFile : null,
        );
    }

    /**
     * Runs $fn, applying whichever instrumentation channels are enabled.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    public function measure(string $operation, callable $fn): mixed
    {
        if (!$this->metrics && !$this->profiling && !$this->logging) {
            return $fn();
        }

        $tags = ['op' => $operation];

        if ($this->profiling) {
            Profiler::start($operation);
        }

        $this->logger?->info('ai.operation.start', $tags);
        $start = \microtime(true);

        try {
            $result = $fn();
            $elapsedMs = (\microtime(true) - $start) * 1000.0;

            if ($this->profiling) {
                Profiler::end($operation);
            }

            if ($this->metrics) {
                Metrics::increment('ai.operation.count', $tags + ['status' => 'ok']);
                Metrics::timing('ai.operation.ms', $elapsedMs, $tags);
            }

            $this->logger?->info('ai.operation.ok', $tags + ['ms' => \round($elapsedMs, 2)]);

            return $result;
        } catch (\Throwable $e) {
            if ($this->profiling) {
                Profiler::end($operation);
            }

            if ($this->metrics) {
                Metrics::increment('ai.operation.count', $tags + ['status' => 'error']);
            }

            $this->logger?->error('ai.operation.error', $tags + ['error' => $e->getMessage()]);

            throw $e;
        }
    }
}
