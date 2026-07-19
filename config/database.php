<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        // ════════════════════════════════════════════════════════════════════
        // PRIMARY CONNECTION BLOCK — drives both local Docker Postgres and
        // production Neon via a single configuration path.
        //
        // HOW IT WORKS:
        //   - Set DB_CONNECTION=pgsql  (the default — never needs changing)
        //   - Set DATABASE_URL to your connection string (see .env.example)
        //     The pooled Neon endpoint (ep-xxx-pooler) is recommended for
        //     production (handles connection storm burst). Use the direct
        //     endpoint (port 5432, no -pooler suffix) for migrations or
        //     bulk DDL where connection lifetime is long.
        //   - Laravel's PostgresConnector reads sslmode from the explicit
        //     config key below and forwards it on the PDO DSN
        //     (PostgresConnector::addSslOptions).
        //   - application_name is set here so Neon console / pg_stat_activity
        //     shows "temple-trust" instead of "psql" as the connected app.
        //
        // For SSL strictness levels, see the comment on 'sslmode' below.
        // ════════════════════════════════════════════════════════════════════
        // ─── Canonical config (Phase A & B completion) ──────────────────────
        // Laravel's parseUrlConfig merges URL-parsed values into the config
        // but does NOT override discrete values. So when DB_HOST is unset
        // (Neon mode — host comes from DATABASE_URL), the host stays null
        // and the URL parse path fills it in. When DB_HOST IS set (local
        // mode — `postgres`), Laravel uses that.
        'pgsql' => [
            'driver'           => 'pgsql',
            'url'              => env('DATABASE_URL'),
            'host'             => env('DB_HOST') ?: null,
            'port'             => env('DB_PORT') ?: null,
            'database'         => env('DB_DATABASE') ?: null,
            'username'         => env('DB_USERNAME') ?: null,
            'password'         => env('DB_PASSWORD') ?: null,
            'charset'          => env('DB_CHARSET', 'utf8'),
            'prefix'           => env('DB_PREFIX', ''),
            'prefix_indexes'   => true,
            'search_path'      => 'public',
            'application_name' => 'temple-trust',

            // Laravel 10's PostgresConnector::addSslOptions() detects the
            // top-level `sslmode` key and appends ";sslmode=<value>" to the
            // PDO DSN. This is THE correct Laravel-10-native path.
            //
            // For Postgres SSL strictness levels:
            //   'disable'    → no SSL (NEVER use for Neon — will be rejected)
            //   'allow'      → prefer plain, fallback to SSL
            //   'prefer'     → try SSL, fall back to plain
            //   'require'    → REQUIRE SSL (Neon minimum)
            //   'verify-ca'  → REQUIRE + verify CA cert
            //   'verify-full'→ REQUIRE + verify CA + verify hostname
            //
            // Neon enforces sslmode=require; we set it as the hard default.
            // Override via DB_SSLMODE env var (set to empty for local Docker
            // Postgres without TLS).
            'sslmode'          => env('DB_SSLMODE', 'require') ?: null,
        ],

        // Alias — DB_CONNECTION=neon maps to the same pgsql block above.
        // Both blocks share the same sslmode forwarding logic via env().
        // The 'neon' block exists for legacy .env files that already set
        // DB_CONNECTION=neon and for teams that prefer the explicit name.
        'neon' => [
            'driver'           => 'pgsql',
            'url'              => env('DATABASE_URL'),
            'host'             => env('DB_HOST') ?: null,
            'port'             => env('DB_PORT') ?: null,
            'database'         => env('DB_DATABASE', 'neondb') ?: null,
            'username'         => env('DB_USERNAME', 'neondb_owner') ?: null,
            'password'         => env('DB_PASSWORD') ?: null,
            'charset'          => env('DB_CHARSET', 'utf8'),
            'prefix'           => env('DB_PREFIX', ''),
            'prefix_indexes'   => true,
            'search_path'      => 'public',
            'application_name' => 'temple-trust',
            'sslmode'          => env('DB_SSLMODE', 'require') ?: null,
        ],

        'sqlite' => [
            'driver'              => 'sqlite',
            'url'                 => env('DATABASE_URL'),
            'database'            => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'              => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix'  => env('REDIS_PREFIX', 'temple_trust_'),
        ],

        'default' => [
            'url'         => env('REDIS_URL'),
            'host'        => env('REDIS_HOST', '127.0.0.1'),
            'username'    => env('REDIS_USERNAME'),
            'password'    => env('REDIS_PASSWORD'),
            'port'        => env('REDIS_PORT', '6379'),
            'database'    => env('REDIS_DB', '0'),
            'timeout'     => (float) env('REDIS_TIMEOUT', 1.5),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 0.5),
            'persistent'  => env('REDIS_PERSISTENT', false),
        ],

        'cache' => [
            'url'         => env('REDIS_URL'),
            'host'        => env('REDIS_HOST', '127.0.0.1'),
            'username'    => env('REDIS_USERNAME'),
            'password'    => env('REDIS_PASSWORD'),
            'port'        => env('REDIS_PORT', '6379'),
            'database'    => env('REDIS_CACHE_DB', '1'),
            'timeout'     => (float) env('REDIS_TIMEOUT', 1.5),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 0.5),
        ],

        'queue' => [
            'url'         => env('REDIS_URL'),
            'host'        => env('REDIS_HOST', '127.0.0.1'),
            'username'    => env('REDIS_USERNAME'),
            'password'    => env('REDIS_PASSWORD'),
            'port'        => env('REDIS_PORT', '6379'),
            'database'    => env('REDIS_QUEUE_DB', '2'),
            'timeout'     => (float) env('REDIS_QUEUE_TIMEOUT', 5),
            'read_timeout' => (float) env('REDIS_QUEUE_READ_TIMEOUT', 30),
        ],

        'session' => [
            'url'         => env('REDIS_URL'),
            'host'        => env('REDIS_HOST', '127.0.0.1'),
            'username'    => env('REDIS_USERNAME'),
            'password'    => env('REDIS_PASSWORD'),
            'port'        => env('REDIS_PORT', '6379'),
            'database'    => env('REDIS_SESSION_DB', '3'),
            'timeout'     => (float) env('REDIS_TIMEOUT', 1.5),
            'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 0.5),
        ],
    ],
];
