<?php

declare(strict_types=1);

namespace App\Providers;

use App\Runtime\Http\Controllers\HealthController;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // Public site (Phase 3+) — web middleware group.
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Artisan commands — web middleware group (so console.php
            // commands have access to session / cookies if needed).
            Route::middleware('web')
                ->group(base_path('routes/console.php'));

            // Runtime kernel (Phase 2) — top-level health endpoint for
            // load balancers. Registered under web group; CSRF check is
            // GET-only so /health bypasses VerifyCsrfToken automatically.
            Route::middleware('web')->get('/health', HealthController::class)
                ->name('runtime.health');

            // Runtime kernel — versioned API surface (Phase 2 ping, future
            // runtime introspection endpoints). Prefix /api/v1.
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/runtime.php'));
        });
    }
}