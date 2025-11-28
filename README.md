# Stellar Security – Application Insights for Laravel

Built by StellarSecurity.com

Lightweight telemetry package for Laravel that sends structured events to your
observability backend (e.g. Azure Application Insights).

It can automatically track:

- Incoming HTTP requests
- Outgoing HTTP calls (Guzzle)
- Database slow queries
- Failed jobs
- Mail failures
- Custom AV / security events

## Installation

```bash
composer require stellarsecurity/application-insights-laravel
```

Laravel will auto-discover the service provider.

Then publish the config:

```bash
php artisan vendor:publish --tag=stellar-ai-config
```

Set your connection string:

```env
STELLAR_AI_CONNECTION_STRING="InstrumentationKey=...;IngestionEndpoint=https://..."
STELLAR_AI_USE_QUEUE=true
```

If you use queues, run a worker:

```bash
php artisan queue:work
```

## Basic usage

```php
use StellarSecurity\ApplicationInsightsLaravel\ApplicationInsights;

app(ApplicationInsights::class)->trackEvent('AV.HashCheck', [
    'client' => 'Stellar Antivirus Desktop',
    'verdict' => 'malware',
]);
```

All automatic telemetry (HTTP / DB / jobs / mail / dependencies) is handled
for you by the service provider.
