<?php

declare(strict_types=1);

namespace Dvpost\TraceMonitor;

use Dvpost\TraceMonitor\Service\TraceMonitorService;

final class TraceMonitor
{
    private static ?TraceMonitorService $traceMonitorService = null;

    public static function openTrace(
        TraceMonitorService $traceMonitorService,
        ?array $context = null,
        ?array $tags = null
    ): void {
        self::$traceMonitorService = $traceMonitorService;
        self::$traceMonitorService->openTrace($context ?? [], $tags ?? []);
    }

    public static function closeTrace(): void
    {
        if (self::$traceMonitorService === null) {
            return;
        }
        self::$traceMonitorService->closeTrace();
    }

    public static function addContextToTrace(array $context): void
    {
        if (self::$traceMonitorService === null) {
            return;
        }
        self::$traceMonitorService->addContextToTrace($context);
    }

    /**
     * @param array $tags ['tag_name'] => 'tag_value'
     * @return void
     */
    public static function addTagsToTrace(array $tags): void
    {
        if (self::$traceMonitorService === null) {
            return;
        }
        self::$traceMonitorService->addTagsToTrace($tags);
    }

    public static function openSpan(
        string $spanName,
        ?array $context = null,
        ?array $tags = null
    ): ?string {
        if (self::$traceMonitorService === null) {
            return null;
        }
        return self::$traceMonitorService->openSpan($spanName, $context ?? [], $tags ?? []);
    }

    public static function closeSpan(string $spanId): void
    {
        if (self::$traceMonitorService === null) {
            return;
        }
        self::$traceMonitorService->closeSpan($spanId);
    }

    public static function openSpanCallback(
        string $spanName,
        ?array $context,
        ?array $tags,
        callable $callback
    ): void {
        $spanId = self::openSpan($spanName, $context, $tags);
        try {
            $callback();
        } finally {
            if ($spanId !== null) {
                self::closeSpan($spanId);
            }
        }
    }
}
