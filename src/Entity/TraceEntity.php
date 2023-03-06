<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Entity;

/**
 * Объект содержит рефференсную информацию об окружении.
 * Может принимать данные вне трейсов
 * Открывается один на все спаны
 */
final class TraceEntity
{
    private string $id;

    private \DateTimeImmutable $openDate;

    /** @param array<string, mixed> $context */
    private array $context;

    /** @param array<string, string> $tags */
    private array $tags;

    /**
     * @param string $id
     * @param \DateTimeImmutable $openedAt
     * @param array $context
     * @param array<string, string> $tags
     */
    public function __construct(
        string $id,
        \DateTimeImmutable $openedAt,
        array $context,
        array $tags
    ) {
        $this->id = $id;
        $this->openDate = $openedAt;
        $this->context = $context;
        $this->tags = $tags;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOpenDate(): \DateTimeImmutable
    {
        return $this->openDate;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function mergeContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function mergeTags(array $spanTags): void
    {
        $this->tags = array_merge($this->tags, $spanTags);
    }
}
