<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Connection string / instrumentation key
    |--------------------------------------------------------------------------
    |
    | You can either set a full Application Insights connection string or
    | just an instrumentation key. The telemetry sender will prefer the
    | connection string if present.
    |
    */

    'connection_string' => env('STELLAR_AI_CONNECTION_STRING'),

    'instrumentation_key' => env('STELLAR_AI_INSTRUMENTATION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Queue usage
    |--------------------------------------------------------------------------
    |
    | If true, telemetry is dispatched to the queue using SendTelemetryJob.
    | If false, telemetry will be sent synchronously on the same request.
    |
    */

    'use_queue' => env('STELLAR_AI_USE_QUEUE', true),

    /*
    |--------------------------------------------------------------------------
    | Sampling & thresholds
    |--------------------------------------------------------------------------
    */

    'http_sample_rate' => env('STELLAR_AI_HTTP_SAMPLE_RATE', 1.0), // 0..1

    'db_slow_ms' => env('STELLAR_AI_DB_SLOW_MS', 500), // only log queries slower than this

    /*
    |--------------------------------------------------------------------------
    | Feature toggles
    |--------------------------------------------------------------------------
    */

    'features' => [
        'http'        => env('STELLAR_AI_FEATURE_HTTP', true),
        'db'          => env('STELLAR_AI_FEATURE_DB', true),
        'jobs'        => env('STELLAR_AI_FEATURE_JOBS', true),
        'mail'        => env('STELLAR_AI_FEATURE_MAIL', true),
        'dependencies'=> env('STELLAR_AI_FEATURE_DEPENDENCIES', true),
    ],

];
