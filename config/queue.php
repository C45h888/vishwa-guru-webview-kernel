<?php

declare(strict_types=1);

return [

    'default' => env('QUEUE_CONNECTION', 'redis'),

    // Retry policy (general-purpose). Per-job overrides via AbstractQueuedJob.
    'general' => [
        'tries'       => (int) env('QUEUE_GENERAL_TRIES', 3),
        'backoff'     => array_map('intval', explode(',', (string) env('QUEUE_GENERAL_BACKOFF', '10,60,300'))),
        'max_time'    => (int) env('QUEUE_WORKER_MAX_TIME', 3600),
        'sleep'       => (int) env('QUEUE_WORKER_SLEEP', 3),
        'timeout'     => (int) env('QUEUE_WORKER_TIMEOUT', 60),
    ],

    // Retry policy (financial-path). Doctrine: fail-fast, surface to ops.
    // Per-job overrides via AbstractQueuedJob $tries=1, $backoff=[0].
    'financial' => [
        'tries'   => (int) env('QUEUE_FINANCIAL_TRIES', 1),
        'backoff' => array_map('intval', explode(',', (string) env('QUEUE_FINANCIAL_BACKOFF', '0'))),
    ],

    // Failed-job retention. Pruned weekly by app/Console/Kernel.php
    // schedule entry (queue:prune-failed --hours=$failed_after_hours).
    'prune' => [
        'failed_after_hours' => (int) env('QUEUE_FAILED_RETENTION_HOURS', 720),
    ],

    // Idempotency fast-path (SETEX-backed dedupe). Doctrine: speedup, not
    // source of truth — the DB UNIQUE constraint on idempotency_keys.key
    // (already in V1 schema) is the authoritative dedupe mechanism.
    // SETEX prevents re-execution within the TTL window; the DB constraint
    // catches anything that slips past the SETEX path (Redis down, races).
    //
    // Subclasses of AbstractQueuedJob override idempotencyKey() and
    // idempotencyTtl() to provide per-job semantics. The defaults here
    // are 24h for general-purpose jobs; webhook-driven jobs typically use
    // 7d (configured via the webhook_ttl slot); financial jobs override
    // idempotencyTtl() to 0 (no dedupe — correctness > convenience).
    'idempotency' => [
        'enabled'     => (bool) env('QUEUE_IDEMPOTENCY_ENABLED', true),
        'default_ttl' => (int) env('QUEUE_IDEMPOTENCY_DEFAULT_TTL', 86400),     // 24h
        'webhook_ttl' => (int) env('QUEUE_IDEMPOTENCY_WEBHOOK_TTL', 604800),    // 7d
        'redis_db'    => (int) env('QUEUE_IDEMPOTENCY_REDIS_DB', 2),            // same as queue DB
        'key_prefix'  => (string) env('QUEUE_IDEMPOTENCY_KEY_PREFIX', 'idem:queue:'),
    ],

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'job_batches',
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table' => 'failed_jobs',
    ],

];