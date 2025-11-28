<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Listeners;

use Illuminate\Queue\Events\JobFailed;
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;

class LogJobFailed
{
    public function __construct(
        protected ApplicationInsights $ai
    ) {}

    public function handle(JobFailed $event): void
    {
        if (! config('stellar-ai.features.jobs', true)) {
            return;
        }

        $this->ai->trackException($event->exception, [
            'job.name' => $event->job->resolveName(),
            'job.queue' => $event->job->getQueue(),
        ]);
    }
}
