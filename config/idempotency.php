<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| HTTP Idempotency-Key Middleware Configuration
|--------------------------------------------------------------------------
|
| Inbound HTTP-layer dedupe (Layer 1 of 3). The Idempotency-Key header
| is read on mutating HTTP requests; duplicate requests within the TTL
| window return the cached response.
|
| Doctrine:
|   - Redis SETEX is the speedup. The DB UNIQUE constraint on
|     idempotency_keys.(key, scope) is the source of truth.
|   - Never throws on Redis-down. Falls through to the DB reserve().
|   - Caches 2xx/4xx responses. 5xx is NOT cached (retryable).
|   - Header value is validated before use (regex: [a-zA-Z0-9\-_]{1,255}).
|
| Layer 2 = queue worker dedupe (landed: AbstractQueuedJob::execute).
| Layer 3 = webhook dedupe (next spec: SET NX EX idem:webhook:*).
|
*/

return [
    'header'      => (string) env('IDEMPOTENCY_KEY_HEADER', 'Idempotency-Key'),
    'key_prefix'  => (string) env('IDEMPOTENCY_KEY_PREFIX', 'idem:http:'),
    'default_ttl' => (int) env('IDEMPOTENCY_TTL_DONATION', 86400),     // 24h
    'webhook_ttl' => (int) env('IDEMPOTENCY_TTL_WEBHOOK', 604800),    // 7d
    'redis_db'    => (int) env('IDEMPOTENCY_REDIS_DB', 1),            // same as cache DB
    'scopes'      => [
        // Map URL prefix → scope. IdempotencyMiddleware::scopeFor() uses this.
        'donation' => ['/api/v1/donate', '/api/v1/donations'],
        'webhook'  => ['/api/v1/webhooks'],
    ],
    'enabled'     => (bool) env('IDEMPOTENCY_ENABLED', true),
];
