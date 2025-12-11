<?php

namespace StellarSecurity\ApplicationInsightsLaravel;

use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use StellarSecurity\ApplicationInsightsLaravel\Jobs\SendTelemetryJob;
use StellarSecurity\ApplicationInsightsLaravel\Helpers\TelemetrySanitizer;

class TelemetrySender
{
    protected array $buffer = [];
    protected int $bufferLimit = 1;

    public function __construct(
        protected ?QueueFactory $queue = null,
        protected ?Client $client = null,
    ) {}

    public function enqueue(array $item): void
    {
        // Sanitize telemetry before it ever enters the buffer or queue
        $item = TelemetrySanitizer::sanitizeItem($item);

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

        $useQueue = (bool) config('stellar-ai.use_queue', true);
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

        if ($conn === '' && $ikey === '') {
            // no telemetry configured, just drop
            return;
        }

        $endpoint = 'https://dc.services.visualstudio.com/v2/track';

        $payload = [];

        foreach ($items as $item) {
            // Always sanitize before sending anything out
            $item = TelemetrySanitizer::sanitizeItem($item);

            if (isset($item['data']['baseType'])) {
                $envelope = $item;

                // Ensure instrumentation key and timestamp are present
                if (empty($envelope['iKey']) && $ikey !== '') {
                    $envelope['iKey'] = $ikey;
                }

                if (empty($envelope['time'])) {
                    $envelope['time'] = gmdate('c');
                }

                $payload[] = $envelope;
                continue;
            }

            $payload[] = [
                'time' => $item['time'] ?? gmdate('c'),
                'name' => $item['name'] ?? 'Custom.Event',
                'iKey' => $ikey !== '' ? $ikey : null,
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
            $response = $client->post($endpoint, [
                'json' => $payload,
            ]);

        } catch (\Throwable $e) {
            // Telemetry must never break the application; failures here are intentionally ignored
        }
    }
}
