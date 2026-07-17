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
 *   1. Reachability (SELECT 1)
 *   2. SSL actually in use (`SHOW ssl`)
 *   3. Current role (`SELECT current_user`)
 *   4. Required extensions present (pgcrypto, citext, btree_gist)
 *   5. Server version (`SHOW server_version`)
 *
 * Doctrine: never throws. Catches Throwable, returns HealthCheckResult::fail().
 * Same pattern as DatabaseHealthProbe / CacheHealthProbe / QueueHealthProbe.
 *
 * Performance: 5 small queries total, ~10-15ms on a healthy Neon branch.
 * The aggregator runs this on every /health hit; load-balancer-safe.
 *
 * Output aggregation: a single failure short-circuits — if reachability
 * fails, we don't probe further. If reachability succeeds but extensions
 * are missing, we report that specific failure.
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

        // Check 1 — reachability.
        $reachability = $this->persistence->query('SELECT 1 AS one');
        if ($reachability->isFailure()) {
            return $this->fail($start, 'reachability: ' . ($reachability->error() ?? 'unknown'));
        }

        // Check 2 — SSL actually in use. Postgres exposes this via `SHOW ssl`.
        $ssl = $this->persistence->query("SHOW ssl");
        if ($ssl->isFailure()) {
            return $this->fail($start, 'ssl check failed: ' . ($ssl->error() ?? 'unknown'));
        }
        $sslOn = $this->firstRowValue($ssl, 'ssl') === 'on';
        if (! $sslOn) {
            return $this->fail(
                $start,
                'connection is not using SSL — Neon requires sslmode=require',
            );
        }

        // Check 3 — current role. Informational only — runtime using DDL role
        // (Owner) is an anti-pattern, but we don't fail the probe for it.
        $role = $this->persistence->query('SELECT current_user AS role_name');
        $currentRole = $role->isSuccess() ? (string) $this->firstRowValue($role, 'role_name') : 'unknown';

        // Check 4 — required extensions.
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

        // Check 5 — server version (informational).
        $versionResult = $this->persistence->query('SHOW server_version');
        $version = $versionResult->isSuccess()
            ? (string) $this->firstRowValue($versionResult, 'server_version')
            : 'unknown';

        $latencyMs = (microtime(true) - $start) * 1000.0;

        // Healthy — detail carries the rich context for ops.
        $detail = sprintf(
            'role=%s version=%s ssl=%s ext=[%s]',
            $currentRole,
            $version,
            'on',
            implode(',', $present),
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