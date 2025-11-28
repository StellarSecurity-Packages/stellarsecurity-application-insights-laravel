<?php

namespace StellarSecurity\ApplicationInsightsLaravel\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;

trait InterceptsGuzzle
{
    protected function instrumentedHttpClient(?ApplicationInsights $ai = null, array $config = []): Client
    {
        $ai = $ai ?: app(ApplicationInsights::class);

        $handler = $config['handler'] ?? null;
        $stack = $handler instanceof HandlerStack ? $handler : HandlerStack::create();

        $stack->push(function (callable $next) use ($ai) {
            return function (RequestInterface $request, array $options) use ($next, $ai) {
                $start = microtime(true);

                return $next($request, $options)->then(
                    function (ResponseInterface $response) use ($request, $ai, $start) {
                        $durationMs = (microtime(true) - $start) * 1000;

                        if (config('stellar-ai.features.dependencies', true)) {
                            $ai->trackDependency(
                                (string) $request->getUri()->getHost(),
                                sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath()),
                                $durationMs,
                                $response->getStatusCode() < 500,
                                [
                                    'dependency.status_code' => $response->getStatusCode(),
                                    'dependency.url' => (string) $request->getUri(),
                                ]
                            );
                        }

                        return $response;
                    },
                    function ($reason) use ($request, $ai, $start) {
                        $durationMs = (microtime(true) - $start) * 1000;

                        if (config('stellar-ai.features.dependencies', true)) {
                            $ai->trackDependency(
                                (string) $request->getUri()->getHost(),
                                sprintf('%s %s', $request->getMethod(), $request->getUri()->getPath()),
                                $durationMs,
                                false,
                                [
                                    'dependency.error' => (string) $reason,
                                ]
                            );
                        }

                        throw $reason;
                    }
                );
            };
        });

        $config['handler'] = $stack;

        return new Client($config);
    }
}
