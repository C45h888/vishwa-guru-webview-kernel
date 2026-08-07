<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Support\Env;
use Dotenv\Dotenv;

$basePath = $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__);

// Explicitly pre-load .env.local into the env repository BEFORE Laravel's
// normal LoadEnvironmentVariables bootstrap runs.
//
// Laravel 11 only reads .env and .env.{APP_ENV} by default — it does NOT
// auto-load .env.local (which is the Symfony/Rails convention used locally).
// For local dev we want .env.local to take priority over .env because the
// project keeps deployment-targeted values (LIVE keys) in .env and
// local-override values (TEST keys) in .env.local / .env.testing.
//
// Pre-loading .env.local here makes Laravel's subsequent env bootstrap
// a no-op for any var already set (Dotenv immutable mode skips overwrites).
//
// Priority (highest → lowest) inside the env repository:
//   1. Real OS env vars                     — immutable, can't be touched
//   2. .env.local           (loaded HERE)   — immutable after this load
//   3. .env                 (loaded by Laravel)
//   4. .env.{APP_ENV}       (loaded by Laravel, e.g. .env.testing)
//
// In production, simply don't ship .env.local and this is a no-op.

$envLocalPath = $basePath . '/.env.local';
if (is_file($envLocalPath)) {
    Dotenv::create(
        Env::getRepository(),
        $basePath,
        '.env.local',
    )->safeLoad();
}

$envTestingPath = $basePath . '/.env.testing';
if (is_file($envTestingPath)) {
    Dotenv::create(
        Env::getRepository(),
        $basePath,
        '.env.testing',
    )->safeLoad();
}

$app = new Application($basePath);

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class,
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class,
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class,
);

return $app;
