<?php

use App\Observability\ScrubEvent;

/**
 * Sentry Laravel SDK configuration file.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [

    // @see https://docs.sentry.io/concepts/key-terms/dsn-explainer/
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // @see https://spotlightjs.com/
    // 'spotlight' => env('SENTRY_SPOTLIGHT', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#logger
    // 'logger' => Sentry\Logger\DebugFileLogger::class, // By default this will log to `storage_path('logs/sentry.log')`

    // The release version of your application
    // Example with dynamic git hash: trim(exec('git --git-dir ' . base_path('.git') . ' log --pretty="%h" -n1 HEAD'))
    'release' => env('SENTRY_RELEASE'),

    // When left empty or `null` the Laravel environment will be used (usually discovered from `APP_ENV` in your `.env`)
    'environment' => env('SENTRY_ENVIRONMENT'),

    // Override the organization ID used for trace continuation checks.
    'org_id' => env('SENTRY_ORG_ID') === null ? null : (int) env('SENTRY_ORG_ID'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#sample_rate
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#traces_sample_rate
    'traces_sample_rate' => null,

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#profiles_sample_rate
    'profiles_sample_rate' => null,

    // Only continue incoming traces when the organization IDs are compatible with this SDK instance.
    'strict_trace_continuation' => env('SENTRY_STRICT_TRACE_CONTINUATION', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#enable_logs
    'enable_logs' => env('SENTRY_ENABLE_LOGS', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#enable_metrics
    'enable_metrics' => env('SENTRY_ENABLE_METRICS', true),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#log_flush_threshold
    'log_flush_threshold' => env('SENTRY_LOG_FLUSH_THRESHOLD') === null ? null : (int) env('SENTRY_LOG_FLUSH_THRESHOLD'),

    // The minimum log level that will be sent to Sentry as logs using the `sentry_logs` logging channel
    'logs_channel_level' => env('SENTRY_LOG_LEVEL', env('SENTRY_LOGS_LEVEL', env('LOG_LEVEL', 'debug'))),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#send_default_pii
    // Hard-wired off: psst is anonymous and no IP, cookie or user data may leave the server.
    'send_default_pii' => false,

    // Every event passes through the scrubber before it is sent (see App\Observability\ScrubEvent).
    'before_send' => [ScrubEvent::class, 'handle'],
    'before_send_transaction' => [ScrubEvent::class, 'dropTransaction'],

    // Configures which data is collected automatically, `null` keeps collecting data based on `send_default_pii`
    'data_collection' => null,

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore_exceptions
    // 'ignore_exceptions' => [],

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore_transactions
    'ignore_transactions' => [
        // Ignore Laravel's default health URL
        '/up',

        // Ignore the Laravel Horizon, Laravel Telescope and Laravel Debugbar routes
        '/^\/(horizon|telescope|_debugbar)(\/|$)/',
    ],

    // Breadcrumb specific configuration
    'breadcrumbs' => [
        // Capture Laravel logs as breadcrumbs
        'logs' => env('SENTRY_BREADCRUMBS_LOGS_ENABLED', true),

        // Capture Laravel cache events (hits, writes etc.) as breadcrumbs
        'cache' => env('SENTRY_BREADCRUMBS_CACHE_ENABLED', true),

        // Capture Livewire components like routes as breadcrumbs
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE_ENABLED', true),

        // Capture SQL queries as breadcrumbs
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED', true),

        // Capture SQL query bindings (parameters) in SQL query breadcrumbs, ignored when `data_collection` is set
        // This option will be migrated to `data_collection.database_query_data` in the next major version
        'sql_bindings' => false,

        // Capture queue job information as breadcrumbs
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO_ENABLED', true),

        // Capture command information as breadcrumbs
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_JOBS_ENABLED', true),

        // Capture HTTP client request information as breadcrumbs
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED', true),

        // Capture send notifications as breadcrumbs
        'notifications' => env('SENTRY_BREADCRUMBS_NOTIFICATIONS_ENABLED', true),
    ],

    // Crons monitoring specific configuration
    'crons' => [
        // Monitor every scheduled task as if `->sentryMonitor()` was called on it
        'monitor_scheduled_tasks' => env('SENTRY_CRONS_MONITOR_SCHEDULED_TASKS', false),

        // Commands, job classes, task names or monitor slugs of tasks that are not monitored automatically, `*` is a wildcard
        'exclude_scheduled_tasks' => [],
    ],

    // Performance monitoring specific configuration
    // Tracing stays off until span scrubbing exists: spans carry SQL and URLs.
    'tracing' => [
        // Trace queue jobs as their own transactions (this enables tracing for queue jobs)
        'queue_job_transactions' => false,

        // Capture queue jobs as spans when executed on the sync driver
        'queue_jobs' => false,

        // Capture SQL queries as spans
        'sql_queries' => false,

        // Capture SQL query bindings (parameters) in SQL query spans, ignored when `data_collection` is set
        // This option will be migrated to `data_collection.database_query_data` in the next major version
        'sql_bindings' => false,

        // Capture where the SQL query originated from on the SQL query spans
        'sql_origin' => false,

        // Define a threshold in milliseconds for SQL queries to resolve their origin
        'sql_origin_threshold_ms' => 100,

        // Capture views rendered as spans
        'views' => false,

        // Capture Livewire components as spans
        'livewire' => false,

        // Capture HTTP client requests as spans
        'http_client_requests' => false,

        // Capture where the HTTP client request originated from on the HTTP client request spans
        'http_client_requests_origin' => false,

        // Define a threshold in milliseconds for HTTP client requests to resolve their origin
        'http_client_requests_origin_threshold_ms' => 250,

        // Capture Laravel cache events (hits, writes etc.) as spans
        'cache' => false,

        // Capture Redis operations as spans (this enables Redis events in Laravel)
        'redis_commands' => false,

        // Capture where the Redis command originated from on the Redis command spans
        'redis_origin' => false,

        // Capture send notifications as spans
        'notifications' => false,

        // Enable tracing for requests without a matching route (404's)
        'missing_routes' => false,

        // Configures if the performance trace should continue after the response has been sent to the user until the application terminates
        // This is required to capture any spans that are created after the response has been sent like queue jobs dispatched using `dispatch(...)->afterResponse()` for example
        'continue_after_response' => false,

        // Capture AI agent interactions as spans (requires laravel/ai)
        'gen_ai' => false,

        // Capture AI invoke_agent spans
        'gen_ai_invoke_agent' => false,

        // Capture AI chat spans
        'gen_ai_chat' => false,

        // Capture AI execute_tool spans
        'gen_ai_execute_tool' => false,

        // Capture AI embeddings spans
        'gen_ai_embeddings' => false,

        // Capture AI evaluate spans for classifications (requires laravel/ai 1.0+)
        'gen_ai_evaluate' => false,

        // Enable the tracing integrations supplied by Sentry (recommended)
        'default_integrations' => false,
    ],

];
