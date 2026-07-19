<?php

declare(strict_types=1);

namespace App\Persistence\Neon\Diagnostics;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\Neon\ValueObjects\NeonConnectionConfig;
use App\Runtime\Diagnostics\HealthCheckResult;
use App\Runtime\Diagnostics\HealthProbe;
use App\Shared\Support\Result;
use Throwable;

/**
 * Neon-specific health probe — supplements the generic DatabaseHealthProbe
 * (which only runs `SELECT 1`) with Neon-specific checks:
 *
 *   0. Configured sslmode is ≥require (read from NeonConnectionConfig —
 *      catches the case where DATABASE_URL was malformed and sslmode
 *      silently fell back to driver default 'prefer' or 'allow')
 *   1. Reachability (SELECT 1) — proves the connection round-trips
 *   2. Required extensions present (pgcrypto, citext, btree_gist)
 *   3. Server version (SHOW server_version)
 *   4. Current role (SELECT current_user)
 *
 * IMPORTANT — About the `SHOW ssl` check that this probe intentionally
 * does NOT perform:
 *
 *   Neon's POOLED endpoint (host contains `-pooler`) routes all traffic
 *   through PgBouncer. PgBouncer terminates TLS at its edge, then opens
 *   an INTERNAL connection to the Postgres backend (often unencrypted,
 *   sometimes over a private network). When a client issues
 *   `SHOW ssl` against such a connection, Postgres reports `ssl=off`
 *   because the wire between PgBouncer's child process and Postgres
 *   is the pooler's internal transport — NOT the public internet.
 *
 *   That makes `SHOW ssl` a FALSE NEGATIVE for pooler connections:
 *   SSL IS enforced at the client ↔ PgBouncer boundary, but Postgres
 *   has no visibility into that. Probing `SHOW ssl` would always fail
 *   against the canonical production endpoint.
 *
 *   Therefore, the SSL check here is delegated entirely to
 *   NeonConnectionConfig::hasSecureSslMode() — which trusts the URL's
 *   sslmode query parameter (verified at the libpq layer when libpq
 *   fails to verify the cert chain, e.g. sslmode=verify-ca would have
 *   rejected an insecure connection at handshake time). If sslmode is
 *   ≥require and the connection round-trips, the channel is secure.
 *
 *   For the DIRECT endpoint (no `-pooler` suffix — used for migrations)
 *   the connection reaches Postgres directly and `SHOW ssl` would
 *   correctly report `on`. The same probe still works there.
 *
 * Doctrine: never throws. Catches Throwable, returns HealthCheckResult::fail().
 *
 * Performance: 4 small queries total, ~10-15ms on a healthy Neon branch.
 * The aggregator runs this on every /health hit; load-balancer-safe.
 *
 * Output aggregation: a single failure short-circuits — if reachability
 * fails, we don't probe further.
 */
final class NeonDiagnosticsProbe implements HealthProbe
{
    private const REQUIRED_EXTENSIONS = ['pgcrypto', 'citext', 'btree_gist'];

    public function __construct(
        private readonly PersistenceAdapterContract $persistence,
        private readonly NeonConnectionConfig $config,
    ) {}

    public function name(): string
    {
        return 'neon';
    }

    public function probe(): HealthCheckResult
    {
        $start = microtime(true);

        // Check 0 — configured sslmode (BEFORE issuing any DB query).
        // If NeonConnectionConfig saw sslmode=allow / prefer / disable in
        // the parsed URL, fail immediately with the exact reason — there
        // is no point reaching the database with an insecure channel.
        if (! $this->config->hasSecureSslMode()) {
            return $this->fail(
                $start,
                sprintf(
                    'configured sslmode=%s — Neon requires sslmode=require '
                    . '(or stricter; verify-ca / verify-full). Update DATABASE_URL '
                    . 'to use the connection string from Neon Console → Connect.',
                    $this->config->sslmode ?? '(unset)',
                ),
            );
        }

        // Check 1 — reachability. Proves we can round-trip a query.
        $reachability = $this->persistence->query('SELECT 1 AS one');
        if ($reachability->isFailure()) {
            return $this->fail($start, 'reachability: ' . ($reachability->error() ?? 'unknown'));
        }

        // Check 2 — current role (informational only). The runtime should
        // connect as `app`, not `owner`. We don't fail for owner (some
        // operators intentionally use it) — we surface it for ops review.
        $role = $this->persistence->query('SELECT current_user AS role_name');
        $currentRole = $role->isOk() ? (string) $this->firstRowValue($role, 'role_name') : 'unknown';

        // Check 3 — required extensions.
        $extensionsResult = $this->persistence->query(
            "SELECT extname FROM pg_extension WHERE extname IN ('pgcrypto','citext','btree_gist')",
        );
        if ($extensionsResult->isFailure()) {
            return $this->fail($start, 'extensions check failed: ' . ($extensionsResult->error() ?? 'unknown'));
        }

        $present = array_map(
            static fn ($row) => $row['extname'] ?? null,
            $extensionsResult->value() ?? [],
        );
        $present = array_filter($present, static fn ($v) => $v !== null);

        $missing = array_values(array_diff(self::REQUIRED_EXTENSIONS, $present));
        if ($missing !== []) {
            return $this->fail(
                $start,
                'missing extensions: ' . implode(', ', $missing)
                    . ' — schema-neon/V1-schema.sql installs these via CREATE EXTENSION',
            );
        }

        // Check 4 — server version (informational).
        $versionResult = $this->persistence->query('SHOW server_version');
        $version = $versionResult->isOk()
            ? (string) $this->firstRowValue($versionResult, 'server_version')
            : 'unknown';

        $latencyMs = (microtime(true) - $start) * 1000.0;

        // Healthy — detail carries the rich context for ops.
        $detail = sprintf(
            'role=%s version=%s sslmode=%s channel_binding=%s ext=[%s] db=%s via_pgbouncer=%s',
            $currentRole,
            $version,
            $this->config->sslmode ?? 'unset',
            $this->config->channelBinding ?? 'unset',
            implode(',', $present),
            $this->config->database ?? 'unset',
            $this->config->isPooled ? 'yes' : 'no',
        );

        return HealthCheckResult::ok('neon', $latencyMs, $detail);
    }

    /**
     * @param  Result<array<int, array<string, mixed>>>  $result
     */
    private function firstRowValue(Result $result, string $key): mixed
    {
        $rows = $result->value();
        if (! is_array($rows) || $rows === []) {
            return null;
        }
        return $rows[0][$key] ?? null;
    }

    private function fail(float $start, string $detail): HealthCheckResult
    {
        $latencyMs = (microtime(true) - $start) * 1000.0;
        return HealthCheckResult::fail('neon', $latencyMs, $detail);
    }
}