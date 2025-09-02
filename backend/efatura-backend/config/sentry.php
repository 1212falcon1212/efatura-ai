<?php

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'release' => env('SENTRY_RELEASE'),
    'environment' => env('APP_ENV', 'local'),
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.2),
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),
    // Basit PII maskeleme için sensitive keys
    'send_default_pii' => false,
    'breadcrumbs' => [
        'sql_queries' => (bool) env('SENTRY_SQL_BREADCRUMBS', false),
    ],
];


