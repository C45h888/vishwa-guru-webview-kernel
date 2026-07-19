<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Webhook Dedupe Middleware Configuration (Layer 3 of 3)
|--------------------------------------------------------------------------
|
| The WebhookDedupeMiddleware sits in front of Razorpay + PayPal inbound
| webhook routes and applies the SETEX dedupe path BEFORE signature
| verification. Doctrine: cheaper check first; expensive HMAC second.
|
| Per-provider event ID headers:
|   - Razorpay: `X-Razorpay-Event-Id` (gateway-issued, unique per delivery)
|   - PayPal:    `PAYPAL-TRANSMISSION-ID` (gateway-issued, unique per delivery)
|
| Doctrine:
|   - Redis SETEX is the speedup. `WebhookEventRepository::reserve()` is
|     the atomic DB primitive (Layer 0 fix). The `webhook_events` table
|     has UNIQUE (provider_code, provider_event_id) — that's the
|     source of truth.
|   - Never throws on Redis-down. Falls through to DB reserve().
|   - Caches 2xx/4xx responses. 5xx NOT cached (webhook gateways retry).
|
| Layer 1 = HTTP Idempotency-Key middleware (landed).
| Layer 2 = Queue worker dedupe (landed).
| Layer 3 = Webhook dedupe (this spec).
*/

return [
    'key_prefix'  => (string) env('WEBHOOK_KEY_PREFIX', 'idem:webhook:'),
    'default_ttl' => (int) env('IDEMPOTENCY_TTL_WEBHOOK', 604800),  // 7d
    'redis_db'    => (int) env('WEBHOOK_REDIS_DB', 2),             // queue DB
    'enabled'     => (bool) env('WEBHOOK_DEDUPE_ENABLED', true),
    'providers'   => [
        'razorpay' => [
            'header' => 'X-Razorpay-Event-Id',
            'path'   => '/api/v1/webhooks/razorpay',
        ],
        'paypal' => [
            'header' => 'PAYPAL-TRANSMISSION-ID',
            'path'   => '/api/v1/webhooks/paypal',
        ],
    ],
];
