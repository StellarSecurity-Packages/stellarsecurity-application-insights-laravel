<?php

namespace StellarSecurity\ApplicationInsightsLaravel;

use Throwable;

class ApplicationInsights
{
    public function __construct(
        protected TelemetrySender $sender,
    ) {}

    public function trackEvent(string $name, array $properties = []): void
    {
        $this->sender->enqueue([
            'type' => 'event',
            'name' => $name,
            'time' => gmdate('c'),
            'properties' => $properties,
        ]);
    }

    public function trackException(Throwable $e, array $properties = []): void
    {
        $props = array_merge($properties, [
            'exception.type' => get_class($e),
            'exception.message' => $e->getMessage(),
            'exception.file' => $e->getFile(),
            'exception.line' => $e->getLine(),
        ]);

        $this->sender->enqueue([
            'type' => 'exception',
            'time' => gmdate('c'),
            'properties' => $props,
        ]);
    }

    public function trackRequest(
        string $method,
        string $url,
        int $statusCode,
        float $durationMs,
        array $properties = []
    ): void {
        $this->sender->enqueue([
            'type' => 'request',
            'time' => gmdate('c'),
            'properties' => array_merge($properties, [
                'request.method' => $method,
                'request.url' => $url,
                'request.status_code' => $statusCode,
                'request.duration_ms' => $durationMs,
            ]),
        ]);
    }

    public function trackDependency(
        string $target,
        string $name,
        float $durationMs,
        bool $success,
        array $properties = []
    ): void {
        $this->sender->enqueue([
            'type' => 'dependency',
            'time' => gmdate('c'),
            'properties' => array_merge($properties, [
                'dependency.target' => $target,
                'dependency.name' => $name,
                'dependency.duration_ms' => $durationMs,
                'dependency.success' => $success,
            ]),
        ]);
    }

    public function trackDbQuery(string $sql, float $durationMs, array $properties = []): void
    {
        $this->sender->enqueue([
            'type' => 'db',
            'time' => gmdate('c'),
            'properties' => array_merge($properties, [
                'db.sql' => $sql,
                'db.duration_ms' => $durationMs,
            ]),
        ]);
    }

    public function flush(): void
    {
        $this->sender->flush();
    }
}
