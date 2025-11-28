<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;

class LogRequestHandled
{
    public function __construct(
        protected ApplicationInsights $ai
    ) {}

    public function handle(RequestHandled $event): void
    {
        if (! config('stellar-ai.features.http', true)) {
            return;
        }

        $request = $event->request;
        $response = $event->response;

        // If middleware already logged, you might skip or keep as lightweight marker.
        $this->ai->trackEvent('http.handled', [
            'method' => $request->getMethod(),
            'path'   => $request->path(),
            'status' => $response->getStatusCode(),
        ]);
    }
}
