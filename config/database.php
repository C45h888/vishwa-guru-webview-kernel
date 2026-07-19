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
        //   - Laravel's PostgresConnector reads sslmode / channel_binding
        //     directly from the URL query string, so sslmode=require and
        //     channel_binding=require in the URL are honoured automatically.
        //   - application_name is set here so Neon console / pg_stat_activity
        //     shows "temple-trust" instead of "psql" as the connected app.
        //
        // NEON MIGRATIONS (php artisan migrate):
        //   Use the DIRECT endpoint (no -pooler suffix) for migrations so
        //   PgBouncer pool churn does not affect schema apply. You can
        //   temporarily set DATABASE_URL to the non-pooled URL just for
        //   the migrate run, or set DB_CONNECTION_URL for the migrate env.
        // ════════════════════════════════════════════════════════════════════
        'pgsql' => [
            'driver'           => 'pgsql',
            'url'              => env('DATABASE_URL'),
            'host'             => env('DB_HOST', '127.0.0.1'),
            'port'             => env('DB_PORT', '5432'),
            'database'         => env('DB_DATABASE', 'temple_trust'),
            'username'         => env('DB_USERNAME', 'temple_trust'),
            'password'         => env('DB_PASSWORD', ''),
            'charset'          => env('DB_CHARSET', 'utf8'),
            'prefix'           => env('DB_PREFIX', ''),
            'prefix_indexes'   => true,
            'search_path'      => 'public',
            'application_name' => 'temple-trust',
        ],

        // Alias — DB_CONNECTION=neon is accepted but maps to the same pgsql
        // block above. Having the alias lets teams migrate incrementally without
        // requiring an immediate .env change everywhere. Prefer DB_CONNECTION=pgsql
        // in new setups.
        'neon' => [
            'driver'           => 'pgsql',
            'url'              => env('DATABASE_URL'),
            'host'             => env('DB_HOST', '127.0.0.1'),
            'port'             => env('DB_PORT', '5432'),
            'database'         => env('DB_DATABASE', 'neondb'),
            'username'         => env('DB_USERNAME', 'neondb_owner'),
            'password'         => env('DB_PASSWORD', ''),
            'charset'          => env('DB_CHARSET', 'utf8'),
            'prefix'           => env('DB_PREFIX', ''),
            'prefix_indexes'   => true,
            'search_path'      => 'public',
            'sslmode'          => 'require',
            'application_name' => 'temple-trust',
        ],

        'sqlite' => [
            'driver'              => 'sqlite',
            'url'                 => env('DATABASE_URL'),
            'database'            => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'             => '',
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
