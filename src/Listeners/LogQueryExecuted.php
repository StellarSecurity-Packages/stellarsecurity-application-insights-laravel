<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;

class LogQueryExecuted
{
    public function __construct(
        protected ApplicationInsights $ai
    ) {}

    public function handle(QueryExecuted $event): void
    {
        if (! config('stellar-ai.features.db', true)) {
            return;
        }

        $threshold = (float) config('stellar-ai.db_slow_ms', 500);
        $durationMs = $event->time;

        if ($durationMs < $threshold) {
            return;
        }

        $sql = $event->sql;
        $this->ai->trackDbQuery($sql, $durationMs, [
            'db.connection' => $event->connectionName,
        ]);
    }
}
