<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Entity;

/**
 * Спан определяет конкретную точку в коде.
 * Содержит бек-трейс и относящуюся к точке в коде информацию.
 * Может содержать дочерние спаны.
 * После закрытия все данные о спане удаляются.
 */
final class OpenSpanEntity
{
    private string $id;

    private \DateTimeImmutable $openedAt;

    private string $name;

    /** @param array<string, mixed> $context */
    private array $context;

    /** @param array<string, string> $tags */
    private array $tags;

    private array $debugTrace;

    private ?OpenSpanEntity $parentSpan;

    /**
     * @param string $id
     * @param \DateTimeImmutable $openedAt
     * @param string $name
     * @param array<string, mixed> $context
     * @param array<string, string> $tags
     * @param OpenSpanEntity|null $parentSpan
     * @param array $debugTrace
     */
    public function __construct(
        string $id,
        \DateTimeImmutable $openedAt,
        string $name,
        array $context,
        array $tags,
        ?OpenSpanEntity $parentSpan,
        array $debugTrace
    ) {
        $this->id = $id;
        $this->openedAt = $openedAt;
        $this->parentSpan = $parentSpan;
        $this->name = $name;
        $this->context = $context;
        $this->tags = $tags;
        $this->debugTrace = $debugTrace;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOpenedAt(): \DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getDebugTrace(): array
    {
        return $this->debugTrace;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getParentSpan(): ?OpenSpanEntity
    {
        return $this->parentSpan;
    }

    public function getIsRoot(): bool
    {
        return $this->parentSpan === null;
    }
}
