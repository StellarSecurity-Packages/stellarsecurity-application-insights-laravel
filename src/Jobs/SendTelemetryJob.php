<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use StellarSecurity\ApplicationInsightsLaravel\TelemetrySender;

class SendTelemetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function handle(TelemetrySender $sender): void
    {
        $sender->sendBatch($this->items);
    }
}
