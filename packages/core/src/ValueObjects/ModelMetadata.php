<?php

declare(strict_types=1);

namespace FerryAI\Core\ValueObjects;

use FerryAI\Core\Exception\ConfigurationException;

readonly class ModelMetadata implements \JsonSerializable
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $author,
        public string $license,
        public array $tags,
        public int $sizeBytes,
        public ?string $architecture = null,
        public ?string $description = null,
        public ?string $homepage = null,
    ) {}

    /**
     * Creates an instance from a JSON string.
     *
     * @throws ConfigurationException when the JSON is invalid
     */
    public static function fromJson(string $json): self
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ConfigurationException('metadata', 'invalid JSON: ' . $exception->getMessage());
        }

        /** @var string[] $tags */
        $tags = $data['tags'] ?? [];

        return new self(
            name: (string) ($data['name'] ?? ''),
            version: (string) ($data['version'] ?? ''),
            author: (string) ($data['author'] ?? ''),
            license: (string) ($data['license'] ?? ''),
            tags: $tags,
            sizeBytes: (int) ($data['sizeBytes'] ?? 0),
            architecture: isset($data['architecture']) ? (string) $data['architecture'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            homepage: isset($data['homepage']) ? (string) $data['homepage'] : null,
        );
    }

    /**
     * Exports to a pretty-printed JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'author' => $this->author,
            'license' => $this->license,
            'tags' => $this->tags,
            'sizeBytes' => $this->sizeBytes,
            'architecture' => $this->architecture,
            'description' => $this->description,
            'homepage' => $this->homepage,
        ];
    }
}
