<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

use FerryAI\Core\Exception\IoException;
use FerryAI\Core\Logger;
use FerryAI\Core\RetryHandler;

final class HuggingFaceClient
{
    private RetryHandler $retry;

    /**
     * @param (\Closure(string): (string|false))|null $httpGet Overridable HTTP GET seam (testing / custom transports)
     */
    public function __construct(
        private readonly ?string $token = null,
        ?RetryHandler $retry = null,
        private readonly ?Logger $logger = null,
        private readonly ?\Closure $httpGet = null,
        private readonly int $maxAttempts = 3,
        private readonly int $retryDelayMs = 1000,
    ) {
        $this->retry = $retry ?? new RetryHandler();
    }

    /**
     * @return string[]
     */
    public function listFiles(string $modelId): array
    {
        $url = \sprintf('https://huggingface.co/api/models/%s', $modelId);

        $context = $this->createContext();
        $response = @\file_get_contents($url, false, $context);

        if ($response === false) {
            return [];
        }

        $data = \json_decode($response, true);

        if (!\is_array($data)) {
            return [];
        }

        $files = [];

        if (isset($data['siblings']) && \is_array($data['siblings'])) {
            foreach ($data['siblings'] as $sibling) {
                if (isset($sibling['rfilename'])) {
                    $files[] = $sibling['rfilename'];
                }
            }
        }

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    public function getModelInfo(string $modelId): array
    {
        $url = \sprintf('https://huggingface.co/api/models/%s', $modelId);

        $context = $this->createContext();
        $response = @\file_get_contents($url, false, $context);

        if ($response === false) {
            return [];
        }

        $data = \json_decode($response, true);

        if (!\is_array($data) || isset($data['error'])) {
            return [];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>             $filters
     * @return array<int, array<string, mixed>>
     */
    public function searchModels(string $query, array $filters = []): array
    {
        $params = \http_build_query(\array_merge(['search' => $query], $filters));
        $url = 'https://huggingface.co/api/models?' . $params;

        $context = $this->createContext();
        $response = @\file_get_contents($url, false, $context);

        if ($response === false) {
            return [];
        }

        $data = \json_decode($response, true);

        if (!\is_array($data) || isset($data['error'])) {
            return [];
        }

        return $data;
    }

    public function downloadFile(string $modelId, string $filename, string $destination): void
    {
        $url = \sprintf(
            'https://huggingface.co/%s/resolve/main/%s',
            $modelId,
            $filename,
        );

        $dir = \dirname($destination);

        if (!\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }

        // Custom transport / tests use the buffered seam; the real network path streams to disk
        // so multi-GB model files never have to be held in memory.
        if ($this->httpGet !== null) {
            $httpGet = $this->httpGet;
            $attempt = 0;
            $data = $this->retry->retry(
                function () use ($httpGet, $url, $modelId, $filename, &$attempt): string {
                    $attempt++;
                    $raw = $httpGet($url);

                    if ($raw === false) {
                        $this->logger?->error('hf.download.failed', ['model' => $modelId, 'file' => $filename, 'attempt' => $attempt]);

                        throw new IoException(\sprintf('Failed to download: %s/%s', $modelId, $filename));
                    }

                    return $raw;
                },
                $this->maxAttempts,
                $this->retryDelayMs,
            );

            if (\file_put_contents($destination, $data) === false) {
                throw new IoException(\sprintf('Cannot write to: %s', $destination));
            }

            return;
        }

        $attempt = 0;
        $this->retry->retry(
            function () use ($url, $destination, $modelId, $filename, &$attempt): bool {
                $attempt++;

                return $this->streamToFile($url, $destination, $modelId, $filename, $attempt);
            },
            $this->maxAttempts,
            $this->retryDelayMs,
        );
    }

    /**
     * Streams an HTTP resource to disk in fixed-size chunks. Throws on any failure so the
     * retry handler can re-attempt.
     */
    private function streamToFile(string $url, string $destination, string $modelId, string $filename, int $attempt): bool
    {
        $in = @\fopen($url, 'rb', false, $this->createContext());

        if ($in === false) {
            $this->logger?->error('hf.download.failed', ['model' => $modelId, 'file' => $filename, 'attempt' => $attempt]);

            throw new IoException(\sprintf('Failed to download: %s/%s', $modelId, $filename));
        }

        $out = @\fopen($destination, 'wb');

        if ($out === false) {
            \fclose($in);

            throw new IoException(\sprintf('Cannot write to: %s', $destination));
        }

        while (!\feof($in)) {
            $chunk = \fread($in, 8192);

            if ($chunk === false) {
                break;
            }

            if ($chunk === '') {
                continue;
            }

            $written = \fwrite($out, $chunk);

            if ($written === false || $written !== \strlen($chunk)) {
                \fclose($in);
                \fclose($out);

                throw new IoException(\sprintf('Failed to write downloaded data to: %s', $destination));
            }
        }

        \fclose($in);
        \fclose($out);

        return true;
    }

    /**
     * @return resource
     */
    private function createContext()
    {
        $options = [
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'follow_location' => 1,
                'ignore_errors' => true,
            ],
        ];

        if ($this->token !== null) {
            $options['http']['header'] = \sprintf(
                "Authorization: Bearer %s\r\n",
                $this->token,
            );
        }

        return \stream_context_create($options);
    }
}
