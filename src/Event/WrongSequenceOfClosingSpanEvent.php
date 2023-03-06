<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Event;

final class WrongSequenceOfClosingSpanEvent extends AbstractTraceMonitorEvent
{
    private string $spanId;

    private array $stack;

    public function __construct(string $spanId, array $stack)
    {
        $this->spanId = $spanId;
        $this->stack = $stack;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getStack(): array
    {
        return $this->stack;
    }
}
