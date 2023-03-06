<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\DataContainer;

use Dvpost\TraceMonitor\Entity\OpenSpanEntity;
use Dvpost\TraceMonitor\Entity\SpanEntityCollection;

final class DataContainerForSetCurrentSpan
{
    private OpenSpanEntity $currentSpan;

    private SpanEntityCollection $parentSpans;

    public function __construct(
        OpenSpanEntity $currentSpan,
        SpanEntityCollection $parentSpans
    ) {
        $this->currentSpan = $currentSpan;
        $this->parentSpans = $parentSpans;
    }

    public function toArray(string $datetimeFormat): array
    {
        $parentSpans = [];
        foreach ($this->parentSpans as $span) {
            $parentSpans[] = $this->spanToArray($span, $datetimeFormat);
        }

        return [
            'span' => $this->spanToArray($this->currentSpan, $datetimeFormat),
            'parentSpans' => $parentSpans,
        ];
    }

    private function spanToArray(OpenSpanEntity $traceEntity, string $datetimeFormat): array
    {
        return [
            'id' => $traceEntity->getId(),
            'parent' => ($traceEntity->getParentSpan() !== null ? $traceEntity->getParentSpan()->getId() : null),
            'openedAt' => $traceEntity->getOpenedAt()->format($datetimeFormat),
            'name' => $traceEntity->getName(),
            'context' => $traceEntity->getContext(),
            'tags' => $traceEntity->getTags(),
            'debugTrace' => $traceEntity->getDebugTrace(),
        ];
    }
}
