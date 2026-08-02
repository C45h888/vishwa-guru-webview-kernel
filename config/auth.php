<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Authentication Configuration
|--------------------------------------------------------------------------
|
| Minimal auth config so that Laravel's Request::user() chain (called by
| ThrottleRequests middleware on the api group, by Inertia for auth prop
| sharing, etc.) resolves without throwing "Auth guard [] is not defined".
|
| Laravel 11 ships without this file by default; auth is configured via
| ->withAuthentication() in bootstrap/app.php. This project predates that
| fluent style so we recreate auth.php with the minimum that keeps the
| request pipeline runnable in V1 (no admin / donor login yet — Phase 4).
|
| The `users` provider points at a null model because no User table exists
| yet. Auth::guard('web')->user() resolves to a SessionGuard which returns
| null when there's no session — that's the expected V1 behavior. Public
| api routes get throttled by IP rather than by user, which is correct
| pre-auth surface.
*/

return [

    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            // Phase 4: Admin Kernel — wired to the canonical User model.
            // The previous `null` was a deliberate V1 placeholder while no
            // users table existed; the table now lands via the Neon MCP
            // migration (2026_08_02_000011_create_users_table) and the
            // SQLite mirror (2026_08_02_000012_create_users_table_sqlite).
            'model' => \App\Models\User::class,
        ],
    ],

    'passwords' => [],

    'password_timeout' => 10800,

];