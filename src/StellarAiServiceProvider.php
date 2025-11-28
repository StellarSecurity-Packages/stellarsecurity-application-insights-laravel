<?php

namespace StellarSecurity\ApplicationInsightsLaravel;

use GuzzleHttp\Client;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Mail\Events\MessageSending;
use StellarSecurity\ApplicationInsightsLaravel\Jobs\SendTelemetryJob;
use StellarSecurity\ApplicationInsightsLaravel\Listeners\LogJobFailed;
use StellarSecurity\ApplicationInsightsLaravel\Listeners\LogMailSending;
use StellarSecurity\ApplicationInsightsLaravel\Listeners\LogQueryExecuted;
use StellarSecurity\ApplicationInsightsLaravel\Listeners\LogRequestHandled;
use StellarSecurity\ApplicationInsightsLaravel\Middleware\LogHttpRequests;

class StellarAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/stellar-ai.php', 'stellar-ai');

        $this->app->singleton(TelemetrySender::class, function ($app) {
            return new TelemetrySender(
                $app->bound(QueueFactory::class) ? $app->make(QueueFactory::class) : null,
                $app->bound(Client::class) ? $app->make(Client::class) : null,
            );
        });

        $this->app->singleton(ApplicationInsights::class, function ($app) {
            return new ApplicationInsights(
                $app->make(TelemetrySender::class)
            );
        });
    }

    public function boot(Dispatcher $events): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/stellar-ai.php' => config_path('stellar-ai.php'),
        ], 'stellar-ai-config');

        // Register middleware alias
        $router = $this->app['router'];
        $router->aliasMiddleware('stellar.ai', LogHttpRequests::class);

        // Optionally push into web + api groups
        $router->pushMiddlewareToGroup('web', LogHttpRequests::class);
        $router->pushMiddlewareToGroup('api', LogHttpRequests::class);

        // Events
        $events->listen(RequestHandled::class, LogRequestHandled::class);
        $events->listen(QueryExecuted::class, LogQueryExecuted::class);
        $events->listen(JobFailed::class, LogJobFailed::class);
        $events->listen(MessageSending::class, LogMailSending::class);
    }
}
