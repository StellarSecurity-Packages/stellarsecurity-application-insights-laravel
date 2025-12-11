<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;
use Throwable;
use StellarSecurity\ApplicationInsightsLaravel\Helpers\HttpExtractor;

class LogHttpRequests
{
    public function __construct(
        protected ApplicationInsights $ai,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $start = microtime(true);

        try {
            /** @var \Symfony\Component\HttpFoundation\Response $response */
            $response    = $next($request);
            $durationMs  = (microtime(true) - $start) * 1000;
            $statusCode  = $response->getStatusCode();
            $success     = $statusCode < 500;

            $this->ai->trackRequest(
                $request->getMethod(),
                HttpExtractor::url($request),
                $statusCode,
                $durationMs,
                [
                    'success'      => $success,
                    'http.method'  => $request->getMethod(),
                    'http.path'    => $request->path(),
                    'http.route'   => optional($request->route())->uri(),
                    'http.status'  => $statusCode,
                    'app.env'      => config('app.env'),
                ]
            );

            return $response;
        } catch (Throwable $e) {
            // This is where we end up when an exception (500) is thrown
            $durationMs = (microtime(true) - $start) * 1000;

            $this->ai->trackRequest(
                $request->getMethod(),
                HttpExtractor::url($request),
                500,
                $durationMs,
                [
                    'success'             => false,
                    'http.method'         => $request->getMethod(),
                    'http.path'           => $request->path(),
                    'exception.type'      => get_class($e),
                    'exception.message'   => $e->getMessage(),
                    'app.env'             => config('app.env'),
                ]
            );


            // Let Laravel handle the exception as usual
            throw $e;
        }
    }
}
