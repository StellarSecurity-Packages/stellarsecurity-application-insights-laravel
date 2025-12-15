<?php

namespace StellarSecurity\ApplicationInsightsLaravel;

use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Facades\Log;
use StellarSecurity\ApplicationInsightsLaravel\Jobs\SendTelemetryJob;

class TelemetrySender
{
    protected array $buffer = [];

    /**
     * Buffer limit before flushing telemetry.
     * Keep it small to avoid losing events on short-lived processes.
     */
    protected int $bufferLimit;

    public function __construct(
        protected ?QueueFactory $queue = null,
        protected ?Client $client = null,
    ) {
        $this->bufferLimit = (int) config('stellar-ai.buffer_limit', 10);
        if ($this->bufferLimit < 1) {
            $this->bufferLimit = 1;
        }
    }

    public function enqueue(array $item): void
    {
        $this->buffer[] = $item;

        if (count($this->buffer) >= $this->bufferLimit) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $batch = $this->buffer;
        $this->buffer = [];

        $useQueue = (bool) config('stellar-ai.use_queue', false);

        // If queue is enabled but no queue factory is available, fall back to direct send.
        if ($useQueue && $this->queue) {
            $this->queue->connection()->push(new SendTelemetryJob($batch));
            return;
        }

        $this->sendBatch($batch);
    }

    public function sendBatch(array $items): void
    {
        $conn = (string) config('stellar-ai.connection_string', '');
        $ikey = (string) config('stellar-ai.instrumentation_key', '');

        $resolved = $this->resolveConfig($conn, $ikey);

        // If nothing is configured, drop silently.
        if ($resolved === null) {
            return;
        }

        [$endpoint, $instrumentationKey] = $resolved;

        $payload = [];

        foreach ($items as $item) {
            // If the item already looks like a full AI envelope, just normalize.
            if (isset($item['data']['baseType'])) {
                $envelope = $item;

                if (empty($envelope['iKey'])) {
                    $envelope['iKey'] = $instrumentationKey;
                }

                if (empty($envelope['time'])) {
                    $envelope['time'] = gmdate('c');
                }

                $payload[] = $envelope;
                continue;
            }

            // Otherwise, wrap as a simple custom event.
            $payload[] = [
                'time' => $item['time'] ?? gmdate('c'),
                'name' => $item['name'] ?? 'Custom.Event',
                'iKey' => $instrumentationKey,
                'data' => [
                    'baseType' => 'EventData',
                    'baseData' => [
                        'ver'        => 2,
                        'name'       => $item['name'] ?? ($item['type'] ?? 'event'),
                        'properties' => $item['properties'] ?? [],
                    ],
                ],
            ];
        }

        $client = $this->client ?: new Client([
            'timeout' => 2.0,
        ]);

        try {
            $client->post($endpoint, [
                'json' => $payload,
            ]);
        } catch (\Throwable $e) {
            // Telemetry must never break the app. Log locally at a low level.
            Log::debug('Application Insights telemetry send failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve ingestion endpoint + instrumentation key from connection string and/or fallback key.
     *
     * Returns:
     *  - [endpointUrl, instrumentationKey]
     *  - null if nothing is configured
     */
    protected function resolveConfig(string $connectionString, string $fallbackIkey): ?array
    {
        $ikey = trim($fallbackIkey);
        $ingestionEndpoint = null;

        if ($connectionString !== '') {
            $parsed = $this->parseConnectionString($connectionString);

            if (!empty($parsed['InstrumentationKey'])) {
                $ikey = trim((string) $parsed['InstrumentationKey']);
            }

            if (!empty($parsed['IngestionEndpoint'])) {
                $ingestionEndpoint = rtrim((string) $parsed['IngestionEndpoint'], '/');
            }
        }

        if ($ikey === '') {
            // Without an instrumentation key, Azure will drop telemetry.
            return null;
        }

        // Default endpoint if none is provided in the connection string.
        $base = $ingestionEndpoint ?: 'https://dc.services.visualstudio.com';

        return [$base . '/v2/track', $ikey];
    }

    /**
     * Parse connection string segments like "Key=Value;Key2=Value2".
     */
    protected function parseConnectionString(string $connectionString): array
    {
        $result = [];

        foreach (explode(';', $connectionString) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || !str_contains($segment, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $segment, 2);
            $result[trim($key)] = trim($value);
        }

        return $result;
    }
}
