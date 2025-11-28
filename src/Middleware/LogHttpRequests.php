<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;

class LogHttpRequests
{
    public function __construct(
        protected ApplicationInsights $ai
    ) {}

    public function handle(Request $request, Closure $next)
    {
        if (! config('stellar-ai.features.http', true)) {
            return $next($request);
        }

        $start = microtime(true);

        $response = $next($request);

        $durationMs = (microtime(true) - $start) * 1000;
        $rate = (float) config('stellar-ai.http_sample_rate', 1.0);

        if ($rate < 1.0 && mt_rand() / mt_getrandmax() > $rate) {
            return $response;
        }

        $this->ai->trackRequest(
            $request->getMethod(),
            $request->fullUrl(),
            $response->getStatusCode(),
            $durationMs,
            [
                'request.ip' => $request->ip(),
                'request.route' => optional($request->route())->getName(),
            ]
        );

        return $response;
    }
}
