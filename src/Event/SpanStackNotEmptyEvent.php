<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Event;

final class SpanStackNotEmptyEvent extends AbstractTraceMonitorEvent
{
    private int $stackCount;

    private array $stack;

    public function __construct(int $stackCount, array $stack)
    {
        $this->stackCount = $stackCount;
        $this->stack = $stack;
    }

    public function getStackCount(): int
    {
        return $this->stackCount;
    }

    public function getStack(): array
    {
        return $this->stack;
    }
}
