<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor\StorageClient;

use Dvpost\TraceMonitor\Event\SendUdpPacket;
use Psr\EventDispatcher\EventDispatcherInterface;
use Dvpost\TraceMonitor\Event\UnableToCreateSocketEvent;
use Dvpost\TraceMonitor\Event\TooLongMessageEvent;
use Dvpost\TraceMonitor\DataContainer\DataContainerForSetTrace;
use Dvpost\TraceMonitor\DataContainer\DataContainerForSetCurrentSpan;
use Dvpost\TraceMonitor\Serialize\MessageSerializer;

final class TraceMonitorStateStorageClient
{
    public const METHOD_SET_TRACE = 'set-trace';
    public const METHOD_ADD_CONTEXT_TO_TRACE = 'add-context-trace';
    public const METHOD_ADD_TAGS_TO_TRACE = 'add-tags-trace';
    public const METHOD_SET_CURRENT_SPAN = 'set-current-span';
    public const METHOD_FREE_PID = 'free-pid';
    public const METHOD_CLOSE_ALL_SPAN = 'close-all-span';

    private string $ip;

    private int $port;

    private string $datetimeFormat;

    private int $maxMessageLen;

    private EventDispatcherInterface $eventDispatcher;

    /** @var false|resource $socket */
    private $socket;

    public function __construct(
        string $ip,
        int $port,
        string $datetimeFormat,
        int $maxMessageLen,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->ip = $ip;
        $this->port = $port;
        $this->datetimeFormat = $datetimeFormat;
        $this->maxMessageLen = $maxMessageLen;
        $this->eventDispatcher = $eventDispatcher;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($this->socket === false) {
            $socketError = socket_strerror(socket_last_error());
            $this->eventDispatcher->dispatch(new UnableToCreateSocketEvent($socketError));
        }
    }

    public function setTrace(int $pid, string $traceId, DataContainerForSetTrace $container): void
    {
        $sentAt = new \DateTimeImmutable();
        $dataPackage = [
            'method' => self::METHOD_SET_TRACE,
            'sentAt' => $sentAt->format($this->datetimeFormat),
            'pid' => (string)$pid,
            'traceId' => $traceId,
            'data' => $container->toArray($this->datetimeFormat),
        ];

        $message = (new MessageSerializer())->serialize($dataPackage);
        self::send($message);
    }

    public function addContextToTrace(int $pid, string $traceId, array $context): void
    {
        $sentAt = new \DateTimeImmutable();
        $dataPackage = [
            'method' => self::METHOD_ADD_CONTEXT_TO_TRACE,
            'sentAt' => $sentAt->format($this->datetimeFormat),
            'pid' => (string)$pid,
            'traceId' => $traceId,
            'data' => $context,
        ];

        $message = (new MessageSerializer())->serialize($dataPackage);
        self::send($message);
    }

    public function addTagsToTrace(int $pid, string $traceId, array $tags): void
    {
        $sentAt = new \DateTimeImmutable();
        $dataPackage = [
            'method' => self::METHOD_ADD_TAGS_TO_TRACE,
            'sentAt' => $sentAt->format($this->datetimeFormat),
            'pid' => (string)$pid,
            'traceId' => $traceId,
            'data' => $tags,
        ];

        $message = (new MessageSerializer())->serialize($dataPackage);
        self::send($message);
    }

    public function setCurrentSpan(int $pid, string $traceId, DataContainerForSetCurrentSpan $container): void
    {
        $sentAt = new \DateTimeImmutable();
        $dataPackage = [
            'method' => self::METHOD_SET_CURRENT_SPAN,
            'sentAt' => $sentAt->format($this->datetimeFormat),
            'pid' => (string)$pid,
            'traceId' => $traceId,
            'data' => $container->toArray($this->datetimeFormat),
        ];

        $message = (new MessageSerializer())->serialize($dataPackage);
        self::send($message);
    }

    public function freePid(int $pid, string $traceId): void
    {
        $sentAt = new \DateTimeImmutable();
        $dataPackage = [
            'method' => self::METHOD_FREE_PID,
            'sentAt' => $sentAt->format($this->datetimeFormat),
            'pid' => (string)$pid,
            'traceId' => $traceId,
            'data' => null,
        ];

        $message = (new MessageSerializer())->serialize($dataPackage);
        self::send($message);
    }

    public function allSpanClose(int $pid, string $traceId): void
    {
        $sentAt = new \DateTimeImmutable();
        $dataPackage = [
            'method' => self::METHOD_CLOSE_ALL_SPAN,
            'sentAt' => $sentAt->format($this->datetimeFormat),
            'pid' => (string)$pid,
            'traceId' => $traceId,
            'data' => null,
        ];

        $message = (new MessageSerializer())->serialize($dataPackage);
        self::send($message);
    }

    private function send(string $message): void
    {
        if ($this->socket === false) {
            return;
        }

        $len = strlen($message);
        if ($len > $this->maxMessageLen) {
            $this->eventDispatcher->dispatch(new TooLongMessageEvent($len, $message));
            return;
        }

        socket_sendto($this->socket, $message, $len, 0, $this->ip, $this->port);
        $this->eventDispatcher->dispatch(new SendUdpPacket($message));
    }

    public function __destruct()
    {
        if ($this->socket === false) {
            return;
        }

        socket_close($this->socket);
    }
}
