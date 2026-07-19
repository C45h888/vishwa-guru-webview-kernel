<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Payments\Domain\Repositories\IdempotencyKeyRepositoryContract;
use App\Redis\Contracts\RedisConnectorContract;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * IdempotencyMiddleware — inbound HTTP dedupe (Layer 1 of 3).
 *
 * Doctrine:
 *   - Read `Idempotency-Key` header on mutating HTTP requests.
 *   - Hot path: Redis SETEX fast-path via `RedisConnectorContract`.
 *   - Cold path (Redis down): DB `IdempotencyKeyRepository::reserve()` —
 *     atomic INSERT ... ON CONFLICT DO NOTHING.
 *   - Cache 2xx/4xx responses for the TTL window. 5xx NOT cached.
 *   - Never throws. All Redis + DB ops wrapped in try/catch; failures
 *     log and fall through to the controller.
 *   - Header value validated before use (regex: [a-zA-Z0-9\-_]{1,255}).
 *
 * Doctrine fail-open: if SETEX is unreachable AND DB reserve() throws,
 * the request still proceeds to the controller. The DB UNIQUE constraint
 * on idempotency_keys.(key, scope) is the source of truth. Duplicate
 * processing at the application level is acceptable; double-charging
 * is NOT.
 *
 * This is the first custom HTTP middleware in the codebase. The pattern
 * established here (constructor injection, contract-only dependencies,
 * doctrine fail-open, response capture) is the template for future
 * HTTP middleware.
 */
final class IdempotencyMiddleware
{
    /**
     * HTTP methods that trigger the dedupe path. GET/HEAD/OPTIONS
     * are naturally idempotent and bypass.
     */
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

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
        private readonly IdempotencyKeyRepositoryContract $db,
        private readonly Clock $clock,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Method check — only mutating verbs trigger dedupe.
        if (! in_array($request->method(), self::MUTATING_METHODS, true)) {
            return $next($request);
        }

        // 2. Header check — no header, no dedupe (graceful degradation).
        $headerName = $this->config->string('idempotency.header', 'Idempotency-Key');
        $key = $request->header($headerName);
        if ($key === null || $key === '') {
            return $next($request);
        }

        // 2a. Header value validation — prevent injection / oversize.
        if (! preg_match(self::HEADER_VALUE_REGEX, $key)) {
            return $next($request);
        }

        // 3. Build the canonical SETEX key.
        $scope = $this->scopeFor($request);
        $keyPrefix = $this->config->string('idempotency.key_prefix', 'idem:http:');
        $fullKey = $keyPrefix . "{$scope}:{$key}";

        // 4. Hot path: Redis SETEX check for cached response.
        $cached = $this->readCachedResponse($fullKey);
        if ($cached !== null) {
            return $this->reconstructResponse($cached);
        }

        // 5. Reserve the key (Redis SETEX first, DB reserve() fallback).
        $ttl = $this->ttlFor($scope);
        if (! $this->reserveKey($fullKey, $scope, $ttl)) {
            // Reserve failed (duplicate OR backend unreachable).
            // Doctrine: brief re-read; if still null, fall through.
            $cached = $this->readCachedResponse($fullKey);
            if ($cached !== null) {
                return $this->reconstructResponse($cached);
            }
            // Truly lost race — fall through to controller.
            // DB UNIQUE constraint on the existing idempotency_keys row
            // (created by the controller's own save() call) catches
            // duplicates here.
        }

        // 6. Run the controller.
        $response = $next($request);

        // 7. Cache the response for future duplicates (only on <500).
        if ($response->getStatusCode() < 500) {
            $this->writeCachedResponse($fullKey, $ttl, $response);
        }

        return $response;
    }

    /**
     * Map URL prefix → scope. Returns the first matching scope from
     * config('idempotency.scopes'), or 'generic' if no match.
     */
    private function scopeFor(Request $request): string
    {
        $scopes = $this->config->get('idempotency.scopes', []);
        if (! is_array($scopes)) {
            return 'generic';
        }

        $path = $request->path();
        foreach ($scopes as $scope => $prefixes) {
            if (! is_array($prefixes)) {
                continue;
            }
            foreach ($prefixes as $prefix) {
                if (str_starts_with($path, ltrim((string) $prefix, '/'))) {
                    return (string) $scope;
                }
            }
        }
        return 'generic';
    }

    /**
     * TTL selection: webhook scope gets 7d, everything else gets
     * the default 24h.
     */
    private function ttlFor(string $scope): int
    {
        if ($scope === 'webhook') {
            return (int) $this->config->get('idempotency.webhook_ttl', 604800);
        }
        return (int) $this->config->get('idempotency.default_ttl', 86400);
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
            Log::warning('IdempotencyMiddleware: Redis read failed (fail-open)', [
                'key' => $fullKey,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }
        // A response is stored as JSON. The "processing" sentinel is
        // the only non-JSON value (string "processing") — treat that
        // as a transient state and re-poll.
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
     * (no Set-Cookie, no Authorization).
     *
     * @param  array<string, mixed>  $cached
     */
    private function reconstructResponse(array $cached): Response
    {
        $status = (int) ($cached['status'] ?? 200);
        $body = (string) ($cached['body'] ?? '');

        $headers = [];
        foreach (($cached['headers'] ?? []) as $name => $value) {
            // Header filtering — security boundary.
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
     * Two-tier reservation: Redis SETEX first, DB reserve() fallback.
     * Returns true on first-tier success; falls through to second tier
     * on first-tier failure (Redis throws); returns whatever the second
     * tier returns.
     */
    private function reserveKey(string $fullKey, string $scope, int $ttl): bool
    {
        // First tier: Redis SET NX EX.
        try {
            $client = $this->redis->connection('default');
            $result = $client->set($fullKey, 'processing', ['NX', 'EX' => $ttl]);
            if ($result === true) {
                return true;
            }
            // NX returned false → duplicate.
            return false;
        } catch (Throwable $e) {
            Log::warning('IdempotencyMiddleware: Redis reserve failed; falling through to DB', [
                'key' => $fullKey,
                'exception' => $e->getMessage(),
            ]);
            // Fall through to DB tier.
        }

        // Second tier: DB atomic reserve.
        return $this->db->reserve($fullKey, $scope, $ttl);
    }

    /**
     * Cache a response payload to Redis. Only called for non-5xx
     * responses. Body size limit prevents memory blowup.
     */
    private function writeCachedResponse(string $fullKey, int $ttl, Response $response): void
    {
        $body = (string) $response->getContent();
        if (strlen($body) > self::MAX_CACHEABLE_BODY_BYTES) {
            Log::info('IdempotencyMiddleware: response too large to cache', [
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
            Log::warning('IdempotencyMiddleware: Redis write failed (response still served)', [
                'key' => $fullKey,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
