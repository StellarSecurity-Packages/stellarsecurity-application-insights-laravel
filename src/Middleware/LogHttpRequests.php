<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;
use Throwable;

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
                $request->fullUrl(),
                $statusCode,
                $durationMs,
                [
                    'success'      => $success,
                    'http.method'  => $request->getMethod(),
                    'http.path'    => $request->path(),
                    'http.route'   => optional($request->route())->uri(),
                    'http.status'  => $statusCode,
                    'ip'           => $request->ip(),
                    'user_agent'   => $request->userAgent(),
                    'app.env'      => config('app.env'),
                ]
            );

            return $response;
        } catch (Throwable $e) {
            // her ender vi, når der kastes exception (500)
            $durationMs = (microtime(true) - $start) * 1000;

            $this->ai->trackRequest(
                $request->getMethod(),
                $request->fullUrl(),
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


            // lad Laravel håndtere fejlen som normalt
            throw $e;
        }
    }
}
