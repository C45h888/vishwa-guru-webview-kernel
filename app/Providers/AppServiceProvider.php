<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Vite is configured (vite.config.ts) to emit the manifest at
        // build/manifest.json. Laravel's default lookup matches that path,
        // so no override is required.
    }
}
