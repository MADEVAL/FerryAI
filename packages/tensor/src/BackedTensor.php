<?php

declare(strict_types=1);

namespace FerryAI\Tensor;

use FerryAI\Core\Contracts\Backend;
use FerryAI\Core\Contracts\Tensor;
use FerryAI\Core\Enums\Device;
use FerryAI\Core\Enums\DType;
use FerryAI\Core\ValueObjects\Shape;

/**
 * Tensor that wraps a backend-produced inner tensor.
 *
 * Structural reads delegate to the inner tensor. Compute/transform operations
 * are routed to the backend, which does not expose them in Phase 1.
 */
final class BackedTensor implements Tensor
{
    public function __construct(
        private Tensor $inner,
        private readonly Backend $backend,
    ) {}

    public function backend(): Backend
    {
        return $this->backend;
    }

    public function inner(): Tensor
    {
        return $this->inner;
    }

    #[\Override]
    public function shape(): Shape
    {
        return $this->inner->shape();
    }

    #[\Override]
    public function dtype(): DType
    {
        return $this->inner->dtype();
    }

    #[\Override]
    public function to(Device $device): self
    {
        return new self($this->inner->to($device), $this->backend);
    }

    #[\Override]
    public function device(): Device
    {
        return $this->inner->device();
    }

    /**
     * @return array<int, mixed>
     */
    #[\Override]
    public function toArray(): array
    {
        return $this->inner->toArray();
    }

    #[\Override]
    public function data(): mixed
    {
        return $this->inner->data();
    }

    #[\Override]
    public function add(Tensor $other): self
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }

    #[\Override]
    public function sub(Tensor $other): self
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }

    #[\Override]
    public function mul(Tensor $other): self
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }

    #[\Override]
    public function matmul(Tensor $other): self
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }

    /**
     * @param int[]|null $axes
     */
    #[\Override]
    public function transpose(?array $axes = null): self
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }

    #[\Override]
    public function reshape(Shape $newShape): self
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }

    /**
     * @param array<int, int|array{int, int}> $slices
     */
    #[\Override]
    public function slice(array $slices): self
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }

    #[\Override]
    public function __clone()
    {
        $this->inner = clone $this->inner;
    }

    #[\Override]
    public function offsetExists(mixed $offset): bool
    {
        return $this->inner->offsetExists($offset);
    }

    #[\Override]
    public function offsetGet(mixed $offset): mixed
    {
        return $this->inner->offsetGet($offset);
    }

    #[\Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->inner->offsetSet($offset, $value);
    }

    #[\Override]
    public function offsetUnset(mixed $offset): void
    {
        $this->inner->offsetUnset($offset);
    }

    #[\Override]
    public function count(): int
    {
        return $this->inner->count();
    }

    /**
     * @return array<int, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->inner->jsonSerialize();
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function __serialize(): array
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\Override]
    public function __unserialize(array $data): void
    {
        throw new \RuntimeException('Not implemented in Phase 1.');
    }
}
