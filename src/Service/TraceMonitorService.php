<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\Service;

use Psr\Log\LoggerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Dvpost\TraceMonitor\Event\SpanStackNotEmptyEvent;
use Dvpost\TraceMonitor\Event\WrongSequenceOfClosingSpanEvent;
use Dvpost\TraceMonitor\Event\TraceNotStarted;
use Dvpost\TraceMonitor\Entity\OpenSpanEntity;
use Dvpost\TraceMonitor\Entity\TraceEntity;
use Dvpost\TraceMonitor\Entity\SpanEntityCollection;
use Dvpost\TraceMonitor\StorageClient\TraceMonitorStateStorageClient;
use Dvpost\TraceMonitor\DataContainer\DataContainerForSetTrace;
use Dvpost\TraceMonitor\DataContainer\DataContainerForSetCurrentSpan;

final class TraceMonitorService
{
    private int $pid;

    private int $bodyMaxSize;

    private int $countParentForDataPackage;

    private int $debugBackTraceLimit;

    private TraceMonitorStateStorageClient $traceMonitorStateStorageClient;

    private EventDispatcherInterface $dispatcher;

    private LoggerInterface $logger;

    private ?OpenSpanEntity $currentSpan = null;

    private ?TraceEntity $trace = null;

    private SpanEntityCollection $spanStack;

    public function __construct(
        int $pid,
        int $bodyMaxSize,
        int $countParentForDataPackage,
        int $debugBackTraceLimit,
        TraceMonitorStateStorageClient $traceMonitorStateStorageClient,
        EventDispatcherInterface $dispatcher,
        LoggerInterface $logger
    ) {
        $this->pid = $pid;
        $this->bodyMaxSize = $bodyMaxSize;
        $this->countParentForDataPackage = $countParentForDataPackage;
        $this->debugBackTraceLimit = $debugBackTraceLimit;
        $this->traceMonitorStateStorageClient = $traceMonitorStateStorageClient;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->spanStack = new SpanEntityCollection();
    }

    public function openTrace(array $context = [], array $tags = []): void
    {
        $trace = new TraceEntity(
            $this->generateUuid4(),
            (new \DateTimeImmutable()),
            $context,
            $tags
        );
        $this->trace = $trace;

        $serverContext = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'host' => $_SERVER['HTTP_HOST'] ?? null,
            'uri' => $_SERVER['REQUEST_URI'] ?? null,
            'body_input' => substr(file_get_contents('php://input'), 0, $this->bodyMaxSize),
            'body_post' => substr(json_encode($_POST), 0, $this->bodyMaxSize),
        ];
        $dataContainer = new DataContainerForSetTrace(
            $this->trace,
            $serverContext
        );
        $this->traceMonitorStateStorageClient->setTrace($this->pid, $this->trace->getId(), $dataContainer);
    }

    public function closeTrace(): void
    {
        if ($this->trace === null) {
            $this->logger->error('Tracing not started. First use the `startTrace` method.');
            $this->dispatcher->dispatch(new TraceNotStarted());
            return;
        }

        $stackCount = $this->spanStack->count();
        if ($stackCount > 0) {
            $stackToEvent = $this->spanStack->all();
            /** @var OpenSpanEntity $span */
            foreach ($this->spanStack as $span) {
                if ($span->getParentSpan() === null) {
                    $this->closeSpanWithChildren($span);
                }
            }
            $this->logger->error('Span stack not empty while close trace');
            $this->dispatcher->dispatch(new SpanStackNotEmptyEvent($stackCount, $stackToEvent));
            $this->spanStack->reset();
        }
        $this->currentSpan = null;

        $this->traceMonitorStateStorageClient->freePid($this->pid, $this->trace->getId());
        $this->trace = null;
    }

    public function addContextToTrace(array $context): void
    {
        if ($this->trace === null) {
            $this->logger->error('Tracing not started. First use the `startTrace` method.');
            $this->dispatcher->dispatch(new TraceNotStarted());
            return;
        }
        $this->trace->mergeContext($context);
        $this->traceMonitorStateStorageClient->addContextToTrace(
            $this->pid,
            $this->trace->getId(),
            $this->trace->getContext()
        );
    }

    public function addTagsToTrace(array $tags): void
    {
        if ($this->trace === null) {
            $this->logger->error('Tracing not started. First use the `startTrace` method.');
            $this->dispatcher->dispatch(new TraceNotStarted());
            return;
        }
        $this->trace->mergeTags($tags);
        $this->traceMonitorStateStorageClient->addTagsToTrace(
            $this->pid,
            $this->trace->getId(),
            $this->trace->getTags()
        );
    }

    public function openSpan(
        string $spanName,
        array $context = [],
        array $tags = []
    ): ?string {
        if ($this->trace === null) {
            $this->logger->error('Tracing not started. First use the `startTrace` method.');
            $this->dispatcher->dispatch(new TraceNotStarted());
            return null;
        }
        $parentSpan = $this->currentSpan ?? null;
        $span = new OpenSpanEntity(
            $this->generateUuid4(),
            (new \DateTimeImmutable),
            $spanName,
            $context,
            $tags,
            $parentSpan,
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->debugBackTraceLimit)
        );
        $this->spanStack->add($span);
        $this->currentSpan = $span;
        $this->setCurrentSpanToStore($this->currentSpan);
        return $span->getId();
    }

    public function closeSpan(string $spanId): void
    {
        if ($this->trace === null) {
            $this->logger->error(
                'Tracing not started. First use the `startTrace` method.',
                [
                    'span_id' => $spanId,
                ]
            );
            $this->dispatcher->dispatch(new TraceNotStarted());
            return;
        }
        $span = $this->spanStack->getById($spanId);
        if ($span === null) {
            $this->logger->error('Span #' . $spanId . ' not found in opened spans');
            return;
        }
        $parentSpan = $span->getParentSpan();
        // Закрываем спан и на всякий случай проверяем и закрываем все дочерние спаны,
        // если обнаружены дочерние спаны это нарушение консистентности.
        $spanStack = $this->spanStack->all();
        if ($this->closeSpanWithChildren($span) > 1) {
            $this->logger->warning('Wrong sequence of closing span');
            $this->dispatcher->dispatch(new WrongSequenceOfClosingSpanEvent($spanId, $spanStack));
        }
        if ($parentSpan === null) {
            $this->currentSpan = null;
            $this->traceMonitorStateStorageClient->allSpanClose($this->pid, $this->trace->getId());
        } else {
            $this->currentSpan = $parentSpan;
            $this->setCurrentSpanToStore($this->currentSpan);
        }
    }

    private function getChildrenMap(SpanEntityCollection $spanCollection): array
    {
        $childrenMap = [];
        /** @var OpenSpanEntity $span */
        foreach ($spanCollection as $span) {
            $parentSpan = $span->getParentSpan();
            if ($parentSpan === null) {
                continue;
            }
            $childrenMap[$parentSpan->getId()][$span->getId()] = $span;
        }
        return $childrenMap;
    }

    private function getChildrenBySpan(array $childrenMap, OpenSpanEntity $selectedSpan): array
    {
        $result = [];
        if (!isset($childrenMap[$selectedSpan->getId()])) {
            return $result;
        }
        /** @var OpenSpanEntity $span */
        foreach ($childrenMap[$selectedSpan->getId()] as $span) {
            $result[$span->getId()] = $span;
            $result = array_merge($result, $this->getChildrenBySpan($childrenMap, $span));
        }

        return $result;
    }

    private function closeSpanWithChildren(OpenSpanEntity $span): int
    {
        $childrenMap = $this->getChildrenMap($this->spanStack);
        $children = $this->getChildrenBySpan($childrenMap, $span);
        // Сначала закрываем самых младших потомков
        $spanToClose = array_merge(array_reverse($children), [$span]);

        /** @var OpenSpanEntity $span */
        foreach ($spanToClose as $span) {
            $this->spanStack->remove($span);
        }

        return count($children);
    }

    private function setCurrentSpanToStore(OpenSpanEntity $span): void
    {
        if ($this->trace === null || $this->currentSpan === null) {
            return;
        }
        $parentSpans = $this->getParentCollectionBySpan(
            $span
        );
        $dataContainer = new DataContainerForSetCurrentSpan(
            $this->currentSpan,
            $parentSpans,
        );
        $this->traceMonitorStateStorageClient->setCurrentSpan($this->pid, $this->trace->getId(), $dataContainer);
    }

    private function getParentCollectionBySpan(
        OpenSpanEntity $span
    ): SpanEntityCollection {
        $parentSpans = new SpanEntityCollection();
        $parentSpan = $span->getParentSpan();
        for ($i = 0; $i < $this->countParentForDataPackage; $i++) {
            if ($parentSpan === null) {
                break;
            }
            $parentSpans->add($parentSpan);
            $parentSpan = $parentSpan->getParentSpan();
        }
        return $parentSpans;
    }

    private function generateUuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
