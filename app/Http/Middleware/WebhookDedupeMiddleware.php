<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Repositories\WebhookEventRepositoryContract;
use App\Redis\Contracts\RedisConnectorContract;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * WebhookDedupeMiddleware — Layer 3 of 3-layer dedupe.
 *
 * Doctrine:
 *   - Read gateway-specific event ID header (Razorpay `X-Razorpay-Event-Id`,
 *     PayPal `PAYPAL-TRANSMISSION-ID`).
 *   - Hot path: Redis SETEX fast-path via `RedisConnectorContract`.
 *   - Cold path (Redis throws): `WebhookEventRepository::reserve()` —
 *     atomic INSERT ... ON CONFLICT DO NOTHING. Doctrine: cheaper check
 *     first; expensive HMAC computation second.
 *   - Caches 2xx/4xx responses. 5xx NOT cached (webhook gateways retry).
 *   - Never throws. All Redis + DB ops wrapped in try/catch; failures log
 *     and fall through to the controller.
 *   - Header value validated before use (regex: [a-zA-Z0-9\-_]{1,255}).
 *
 * This is the second custom HTTP middleware in the codebase. The
 * pattern (constructor injection, contract-only dependencies, doctrine
 * fail-open, response capture) was established by IdempotencyMiddleware
 * (Layer 1) and is mirrored here.
 *
 * The webhook controller (when it lands in Phase 3) does signature
 * verification + downstream dispatch AFTER this middleware gates the
 * request.
 */
final class WebhookDedupeMiddleware
{
    /**
     * Header value validation regex: alphanumerics + hyphen + underscore,
     * 1-255 chars. Prevents CRLF injection, oversize keys, special chars.
     */
    private const HEADER_VALUE_REGEX = '/^[a-zA-Z0-9\-_]{1,255}$/';

    /**
     * Max response body size to cache (1MB). Larger responses are not
     * cached — caller gets fresh execution on retry (DB UNIQUE catches).
     */
    private const MAX_CACHEABLE_BODY_BYTES = 1_048_576;

    public function __construct(
        private readonly RedisConnectorContract $redis,
        private readonly ConfigurationContract $config,
        private readonly WebhookEventRepositoryContract $webhookRepo,
        private readonly Clock $clock,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Provider resolution from URL path.
        $provider = $this->providerFor($request);
        if ($provider === null) {
            // Unknown webhook URL — pass through; controller will 404.
            return $next($request);
        }

        // 2. Read the gateway-specific event ID header.
        $headerName = $this->headerFor($provider);
        $eventId = $request->header($headerName);
        if ($eventId === null || $eventId === '') {
            // No event ID — gateways always send this. Pass through; the
            // controller's signature verification will likely reject
            // (signature verification also needs the event ID context).
            return $next($request);
        }

        // 2a. Header value validation — prevent injection / oversize.
        if (! preg_match(self::HEADER_VALUE_REGEX, $eventId)) {
            return $next($request);
        }

        // 3. Build the canonical SETEX key.
        $keyPrefix = $this->config->string('webhook.key_prefix', 'idem:webhook:');
        $fullKey = $keyPrefix . "{$provider->value}:{$eventId}";

        // 4. Hot path: Redis SETEX check for cached response.
        $cached = $this->readCachedResponse($fullKey);
        if ($cached !== null) {
            return $this->reconstructResponse($cached);
        }

        // 5. Reserve the key (Redis SETEX first, DB reserve() fallback).
        $ttl = $this->config->integer('webhook.default_ttl', 604800); // 7d
        if (! $this->reserveKey($provider, $eventId, $fullKey, $ttl)) {
            // Reserve failed (duplicate OR backend unreachable).
            $cached = $this->readCachedResponse($fullKey);
            if ($cached !== null) {
                return $this->reconstructResponse($cached);
            }
            // Truly lost race — fall through to controller.
            // DB UNIQUE constraint catches any downstream record() collision.
        }

        // 6. Run the controller (does signature verification + dispatch).
        $response = $next($request);

        // 7. Cache the response for future duplicates (only on <500).
        // 5xx is retryable — gateways auto-retry; caching prevents retry.
        if ($response->getStatusCode() < 500) {
            $this->writeCachedResponse($fullKey, $ttl, $response);
        }

        return $response;
    }

    /**
     * Map URL path to PaymentProvider. Returns null if the URL doesn't
     * match any configured webhook route.
     */
    private function providerFor(Request $request): ?PaymentProvider
    {
        $providers = $this->config->get('webhook.providers', []);
        if (! is_array($providers)) {
            return null;
        }

        $path = '/' . ltrim($request->path(), '/');
        foreach ($providers as $name => $config) {
            if (! is_array($config)) {
                continue;
            }
            $expectedPath = '/' . ltrim((string) ($config['path'] ?? ''), '/');
            if ($path === $expectedPath || str_starts_with($path, $expectedPath . '/')) {
                $provider = PaymentProvider::tryFrom((string) $name);
                if ($provider !== null) {
                    return $provider;
                }
            }
        }
        return null;
    }

    /**
     * Map provider to gateway event ID header name.
     */
    private function headerFor(PaymentProvider $provider): string
    {
        $providers = $this->config->get('webhook.providers', []);
        if (! is_array($providers)) {
            return '';
        }
        $cfg = $providers[$provider->value] ?? [];
        return (string) ($cfg['header'] ?? '');
    }

    /**
     * Two-tier reservation: Redis SETEX first, DB reserve() fallback.
     * Returns true on first-tier success; falls through to second tier
     * on first-tier failure (Redis throws); returns whatever the second
     * tier returns.
     */
    private function reserveKey(PaymentProvider $provider, string $eventId, string $fullKey, int $ttl): bool
    {
        // First tier: Redis SET NX EX.
        try {
            $client = $this->redis->connection('default');
            $result = $client->set($fullKey, 'processing', ['NX', 'EX' => $ttl]);
            if ($result === true) {
                return true;
            }
            return false;
        } catch (Throwable $e) {
            Log::warning('WebhookDedupeMiddleware: Redis reserve failed; falling through to DB', [
                'key' => $fullKey,
                'exception' => $e->getMessage(),
            ]);
            // Fall through to DB tier.
        }

        // Second tier: WebhookEventRepository::reserve() (Phase 0 atomic primitive).
        return $this->webhookRepo->reserve($provider, $eventId, $ttl);
    }

    /**
     * Read a cached response payload from Redis. Returns null on miss,
     * decode error, or backend error (fail-open).
     *
     * @return array<string, mixed>|null
     */
    private function readCachedResponse(string $fullKey): ?array
    {
        try {
            $client = $this->redis->connection('default');
            $raw = $client->get($fullKey);
        } catch (Throwable $e) {
            Log::warning('WebhookDedupeMiddleware: Redis read failed (fail-open)', [
                'key' => $fullKey,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }
        // "processing" sentinel — transient state, re-poll.
        if ($raw === 'processing') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Reconstruct a Response from a cached payload. Doctrine-clean:
     * only Content-Type / Cache-Control / X-Request-Id are replayed
     * (no Set-Cookie, no Authorization, no X-Razorpay-Signature — gateways
     * re-sign on retry).
     *
     * @param  array<string, mixed>  $cached
     */
    private function reconstructResponse(array $cached): Response
    {
        $status = (int) ($cached['status'] ?? 200);
        $body = (string) ($cached['body'] ?? '');

        $headers = [];
        foreach (($cached['headers'] ?? []) as $name => $value) {
            if (! in_array($name, ['content-type', 'cache-control', 'x-request-id'], true)) {
                continue;
            }
            $headers[$name] = (string) $value;
        }

        $response = new Response($body, $status, $headers);
        $response->headers->set('X-Idempotency-Replay', 'true');

        return $response;
    }

    /**
     * Cache a response payload to Redis. Only called for non-5xx
     * responses. Body size limit prevents memory blowup.
     */
    private function writeCachedResponse(string $fullKey, int $ttl, Response $response): void
    {
        $body = (string) $response->getContent();
        if (strlen($body) > self::MAX_CACHEABLE_BODY_BYTES) {
            Log::info('WebhookDedupeMiddleware: response too large to cache', [
                'key' => $fullKey,
                'body_bytes' => strlen($body),
            ]);
            return;
        }

        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            $lower = strtolower($name);
            if (! in_array($lower, ['content-type', 'cache-control', 'x-request-id'], true)) {
                continue;
            }
            $headers[$lower] = implode(', ', $values);
        }

        $payload = json_encode([
            'status'    => $response->getStatusCode(),
            'headers'   => $headers,
            'body'      => $body,
            'cached_at' => $this->clock->now()->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        try {
            $client = $this->redis->connection('default');
            $client->set($fullKey, $payload, ['EX' => $ttl]);
        } catch (Throwable $e) {
            // Doctrine: log + continue. Response still returns to caller.
            Log::warning('WebhookDedupeMiddleware: Redis write failed (response still served)', [
                'key' => $fullKey,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
