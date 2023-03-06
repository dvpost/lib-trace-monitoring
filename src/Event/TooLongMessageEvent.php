<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Event;

final class TooLongMessageEvent extends AbstractTraceMonitorEvent
{
    private int $len;

    private string $message;

    public function __construct(int $len, string $message)
    {
        $this->len = $len;
        $this->message = $message;
    }

    public function getLen(): int
    {
        return $this->len;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
