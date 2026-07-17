<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [
        'pgsql' => [
            'driver'         => 'pgsql',
            'url'            => env('DATABASE_URL'),
            'host'           => env('DB_HOST', '127.0.0.1'),
            'port'           => env('DB_PORT', '5432'),
            'database'       => env('DB_DATABASE', 'temple_trust'),
            'username'       => env('DB_USERNAME', 'temple_trust'),
            'password'       => env('DB_PASSWORD', ''),
            'charset'        => env('DB_CHARSET', 'utf8'),
            'prefix'         => env('DB_PREFIX', ''),
            'prefix_indexes' => true,
            'search_path'    => 'public',
            // sslmode is intentionally NOT a static literal here.
            // Laravel's PostgresConnector parses DATABASE_URL and applies
            // its sslmode query parameter when the URL is present. A hard-
            // coded 'prefer' here would silently downgrade sslmode=require
            // for Neon. For local Docker Postgres without TLS, omit it
            // (driver default). For Neon, use the dedicated 'neon' block
            // below.
        ],

        // Neon connection preset — forces sslmode=require, sets a stable
        // application_name, and applies channel_binding=require. Selecting
        // this block via DB_CONNECTION=neon (or DB::connection('neon'))
        // ensures every query path through Neon uses SSL. All Neon-specific
        // diagnostics (App\Persistence\Neon\Diagnostics\NeonDiagnosticsProbe)
        // read through this connection block.
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

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

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
