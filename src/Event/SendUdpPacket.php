<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Event;

final class SendUdpPacket extends AbstractTraceMonitorEvent
{
    private string $message;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}
