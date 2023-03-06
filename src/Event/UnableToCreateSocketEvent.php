<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Event;

final class UnableToCreateSocketEvent extends AbstractTraceMonitorEvent
{
    private string $socketError;

    public function __construct(string $socketError)
    {
        $this->socketError = $socketError;
    }

    public function getSocketError(): string
    {
        return $this->socketError;
    }
}
