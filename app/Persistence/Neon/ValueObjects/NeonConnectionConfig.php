<?php

declare(strict_types=1);

namespace App\Persistence\Neon\ValueObjects;

use App\Shared\Contracts\ConfigurationContract;

/**
 * Immutable snapshot of the parsed Neon connection — what
 * `temple:neon:ping` shows and what `NeonDiagnosticsProbe` introspects.
 *
 * Doctrine: the password is **NEVER** included in this value object.
 * Operators run `temple:neon:ping` to see connection metadata; showing
 * the password in any command output or log line is a doctrine violation.
 * Tests verify this explicitly.
 *
 * Parses `DATABASE_URL` via PHP's built-in `parse_url()` — NOT Laravel's
 * URL parser — so the parsing is deterministic and free of framework
 * quirks. Missing fields are stored as `null` and rendered as "(not set)"
 * in CLI output so operators can spot configuration gaps.
 */
final class NeonConnectionConfig
{
    private function __construct(
        public readonly ?string $host,
        public readonly ?string $database,
        public readonly ?string $username,
        public readonly ?string $sslmode,
        public readonly ?string $channelBinding,
        public readonly ?string $applicationName,
        public readonly NeonRole $role,
        public readonly bool $isPooled,
    ) {}

    /**
     * Build a config from the application's ConfigurationContract.
     *
     * Reads DATABASE_URL via the `database.connections.pgsql.url` key.
     * Laravel auto-populates this key by parsing DATABASE_URL when the
     * env var is set. This means the config is correct for both:
     *   - DB_CONNECTION=pgsql  (primary; uses pgsql.url)
     *   - DB_CONNECTION=neon   (alias; also resolves to pgsql block at runtime)
     *
     * Falls back to discrete DB_HOST / DB_DATABASE / DB_USERNAME env keys
     * when DATABASE_URL is absent (local Docker Postgres workflow).
     *
     * Role is read from NEON_ROLE env key (owner=DDL only, app=runtime).
     * NeonConnectionConfig itself does NOT prevent DDL on the app role —
     * that is enforced by the role grant model in Neon, not in code.
     */
    public static function fromConfig(ConfigurationContract $config): self
    {
        // Read DATABASE_URL from the pgsql connection block — Laravel stores
        // the parsed URL there regardless of DB_CONNECTION=pgsql or DB_CONNECTION=neon.
        // The 'neon' key in config is an alias; the actual connection always
        // flows through 'pgsql' at the Laravel layer.
        $databaseUrl = $config->get('database.connections.pgsql.url');

        $parsed = is_string($databaseUrl) ? parse_url($databaseUrl) : null;

        $host = is_array($parsed) ? ($parsed['host'] ?? null) : null;
        $database = is_array($parsed) ? ltrim($parsed['path'] ?? '', '/') ?: null : null;
        $username = is_array($parsed) ? ($parsed['user'] ?? null) : null;

        // Parse query-string flags (sslmode, channel_binding).
        $sslmode = null;
        $channelBinding = null;
        if (is_array($parsed) && isset($parsed['query'])) {
            parse_str($parsed['query'], $queryFlags);
            $sslmode = isset($queryFlags['sslmode']) ? (string) $queryFlags['sslmode'] : null;
            $channelBinding = isset($queryFlags['channel_binding'])
                ? (string) $queryFlags['channel_binding']
                : null;
        }

        // Fallback to discrete env keys when DATABASE_URL is missing.
        // All discrete keys are resolved via env() in config/database.php against
        // the pgsql block, so we read from pgsql.* for fallbacks.
        if ($host === null) {
            $host = $config->get('database.connections.pgsql.host');
        }
        if ($database === null) {
            $database = $config->get('database.connections.pgsql.database');
        }
        if ($username === null) {
            $username = $config->get('database.connections.pgsql.username');
        }
        if ($sslmode === null) {
            $sslmode = $config->get('database.connections.pgsql.sslmode');
        }

        // Connection pool detection: Neon PgBouncer sets ?pgbouncer=true
        // on the DATABASE_URL.
        $isPooled = false;
        if (is_array($parsed) && isset($parsed['query'])) {
            parse_str($parsed['query'], $queryFlags);
            $isPooled = isset($queryFlags['pgbouncer']) && $queryFlags['pgbouncer'] !== 'false';
        }

        // Application name is set in config (we always set it for the
        // `neon` connection block) — read it from there.
        $applicationName = $config->get('database.connections.neon.application_name');

        // Role parsed from NEON_ROLE env key.
        $roleValue = (string) ($config->get('services.neon.role') ?? 'app');
        $role = NeonRole::tryFrom($roleValue) ?? NeonRole::App;

        return new self(
            host: $host !== null ? (string) $host : null,
            database: $database !== null ? (string) $database : null,
            username: $username !== null ? (string) $username : null,
            sslmode: $sslmode !== null ? (string) $sslmode : null,
            channelBinding: $channelBinding !== null ? (string) $channelBinding : null,
            applicationName: $applicationName !== null ? (string) $applicationName : null,
            role: $role,
            isPooled: $isPooled,
        );
    }

    /**
     * Whether the connection is configured with the minimum
     * requirements for Neon: sslmode=require (or stricter).
     */
    public function hasSecureSslMode(): bool
    {
        if ($this->sslmode === null) {
            return false;
        }
        $mode = strtolower($this->sslmode);
        return in_array($mode, ['require', 'verify-ca', 'verify-full'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'host'              => $this->host ?? '(not set)',
            'database'          => $this->database ?? '(not set)',
            'username'          => $this->username ?? '(not set)',
            'role'              => $this->role->label(),
            'sslmode'           => $this->sslmode ?? '(not set)',
            'channel_binding'   => $this->channelBinding ?? '(not set)',
            'application_name'  => $this->applicationName ?? '(not set)',
            'is_pooled'         => $this->isPooled,
        ];
    }
}