<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\DataContainer;

use Dvpost\TraceMonitor\Entity\TraceEntity;

final class DataContainerForSetTrace
{
    private TraceEntity $trace;

    private array $serverContext;

    public function __construct(
        TraceEntity $trace,
        array $serverContext
    ) {
        $this->trace = $trace;
        $this->serverContext = $serverContext;
    }

    public function toArray(string $datetimeFormat): array
    {
        return [
            'serverContext' => $this->serverContext,
            'context' => $this->trace->getContext(),
            'tags' => $this->trace->getTags(),
            'openedAt' => $this->trace->getOpenDate()->format($datetimeFormat),
        ];
    }
}
