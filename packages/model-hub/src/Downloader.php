<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

use FerryAI\Core\Exception\IoException;
use FerryAI\Core\Logger;
use FerryAI\Core\RetryHandler;

final class Downloader
{
    private bool $cancelled = false;

    private RetryHandler $retry;

    public function __construct(
        ?RetryHandler $retry = null,
        private readonly ?Logger $logger = null,
        private readonly int $maxAttempts = 3,
        private readonly int $retryDelayMs = 1000,
    ) {
        $this->retry = $retry ?? new RetryHandler();
    }

    public function download(string $url, string $destination, ?callable $onProgress = null): void
    {
        $this->cancelled = false;
        $attempt = 0;

        $this->retry->retry(
            function () use ($url, $destination, $onProgress, &$attempt): void {
                $attempt++;
                $this->logger?->info('download.attempt', ['url' => $url, 'attempt' => $attempt]);

                try {
                    $this->doDownload($url, $destination, $onProgress);
                } catch (\Throwable $e) {
                    $this->logger?->error('download.failed', [
                        'url' => $url,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            },
            $this->maxAttempts,
            $this->retryDelayMs,
        );
    }

    private function doDownload(string $url, string $destination, ?callable $onProgress): void
    {
        $context = \stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 300,
                'follow_location' => 1,
            ],
        ]);

        $handle = @\fopen($url, 'rb', false, $context);

        if ($handle === false) {
            throw new IoException(\sprintf('Cannot open URL: %s', $url));
        }

        $dir = \dirname($destination);

        if (!\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }

        $outHandle = \fopen($destination, 'wb');

        if ($outHandle === false) {
            \fclose($handle);

            throw new IoException(\sprintf('Cannot write to: %s', $destination));
        }

        $downloaded = 0;

        while (!\feof($handle)) {
            /** @phpstan-ignore booleanNot.alwaysTrue */
            if ($this->cancelled) {
                break;
            }

            $chunk = \fread($handle, 8192);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $written = \fwrite($outHandle, $chunk);

            if ($written === false || $written !== \strlen($chunk)) {
                \fclose($outHandle);
                \fclose($handle);

                throw new IoException(\sprintf('Failed to write downloaded data to: %s', $destination));
            }

            $downloaded += \strlen($chunk);

            if ($onProgress !== null) {
                $onProgress($downloaded, -1);
            }
        }

        \fclose($outHandle);
        \fclose($handle);

        /** @phpstan-ignore booleanAnd.leftAlwaysFalse */
        if ($this->cancelled && \file_exists($destination)) {
            \unlink($destination);
        }
    }

    /**
     * @return \Generator<int, array{progress: float, downloaded: int, total: int}>
     */
    public function downloadWithProgress(string $url, string $destination): \Generator
    {
        $this->cancelled = false;

        yield ['progress' => 0.0, 'downloaded' => 0, 'total' => -1];

        $context = \stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 300,
                'follow_location' => 1,
            ],
        ]);

        $handle = @\fopen($url, 'rb', false, $context);

        if ($handle === false) {
            throw new IoException(\sprintf('Cannot open URL: %s', $url));
        }

        $dir = \dirname($destination);

        if (!\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }

        $outHandle = \fopen($destination, 'wb');

        if ($outHandle === false) {
            \fclose($handle);

            throw new IoException(\sprintf('Cannot write to: %s', $destination));
        }

        $downloaded = 0;

        while (!\feof($handle)) {
            if ($this->cancelled) {
                break;
            }

            $chunk = \fread($handle, 8192);

            if ($chunk === false || $chunk === '') {
                break;
            }

            $written = \fwrite($outHandle, $chunk);

            if ($written === false || $written !== \strlen($chunk)) {
                \fclose($outHandle);
                \fclose($handle);

                throw new IoException(\sprintf('Failed to write downloaded data to: %s', $destination));
            }

            $downloaded += \strlen($chunk);

            yield ['progress' => 0.0, 'downloaded' => $downloaded, 'total' => -1];
        }

        \fclose($outHandle);
        \fclose($handle);

        if ($this->cancelled && \file_exists($destination)) {
            \unlink($destination);
        }

        yield ['progress' => 1.0, 'downloaded' => $downloaded, 'total' => $downloaded];
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }
}
