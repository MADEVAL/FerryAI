<?php

declare(strict_types=1);

namespace FerryAI\ModelHub;

final class HuggingFaceClient
{
    public function __construct(
        private ?string $token = null,
    ) {}

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

        $context = $this->createContext();

        $data = @\file_get_contents($url, false, $context);

        if ($data === false) {
            throw new \RuntimeException(\sprintf('Failed to download: %s/%s', $modelId, $filename));
        }

        $dir = \dirname($destination);

        if (!\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }

        \file_put_contents($destination, $data);
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
