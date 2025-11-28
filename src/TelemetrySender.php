<?php

namespace StellarSecurity\ApplicationInsightsLaravel;

use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use StellarSecurity\ApplicationInsightsLaravel\Jobs\SendTelemetryJob;

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
            // Hvis item ALLEREDE er et fuldt AI-envelope (fx RequestData / ExceptionData / etc),
            // så sender vi det direkte og rører det ikke.
            if (isset($item['data']['baseType'])) {
                $envelope = $item;

                // Sørg for iKey og time
                if (empty($envelope['iKey']) && $ikey !== '') {
                    $envelope['iKey'] = $ikey;
                }

                if (empty($envelope['time'])) {
                    $envelope['time'] = gmdate('c');
                }

                $payload[] = $envelope;
                continue;
            }

            // Ellers: wrap som custom EventData (fallback)
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
            // Telemetry må ALDRIG smadre appen – men vi kan godt logge lokalt
        }
    }
}
