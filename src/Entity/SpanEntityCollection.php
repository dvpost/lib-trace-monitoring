<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Entity;

use ArrayIterator;
use IteratorAggregate;

/**
 * Коллекция спанов для типизации массива
 */
final class SpanEntityCollection implements IteratorAggregate
{
    /** @var OpenSpanEntity[] $spanCollection */
    private array $spanCollection = [];

    public function add(OpenSpanEntity $span): void
    {
        $this->spanCollection[$span->getId()] = $span;
    }

    public function remove(OpenSpanEntity $span): void
    {
        if ($this->isIdExists($span->getId()) !== true) {
            return;
        }
        unset($this->spanCollection[$span->getId()]);
    }

    public function reset(): void
    {
        $this->spanCollection = [];
    }

    public function count(): int
    {
        return count($this->spanCollection);
    }

    public function isIdExists(string $id): bool
    {
        return array_key_exists($id, $this->spanCollection);
    }

    public function getById(string $id): ?OpenSpanEntity
    {
        return $this->spanCollection[$id] ?? null;
    }

    public function all(): array
    {
        return $this->spanCollection;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->spanCollection);
    }
}
