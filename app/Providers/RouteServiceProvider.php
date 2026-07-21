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

            // Public campaigns routes — Sub-project 1 reserves the URL
            // namespace (campaigns.index, campaigns.show). Sub-project 3
            // wires real controllers behind these paths.
            Route::middleware('web')
                ->group(base_path('routes/campaigns.php'));

            // Public gallery routes — reserved in Sub-project 1, wired
            // in Sub-project 3.
            Route::middleware('web')
                ->group(base_path('routes/gallery.php'));

            // Public events routes — reserved in Sub-project 1, wired
            // in Sub-project 3.
            Route::middleware('web')
                ->group(base_path('routes/events.php'));

            // Public receipt routes — Sub-project 3 (Phase 3).
            // Single GET /receipts/{receiptNumber} with regex constraint.
            Route::middleware('web')
                ->group(base_path('routes/receipts.php'));

            // Donation UI pages — Sub-project 3 (Phase 3).
            // GET /donate + GET /donate/success served as Inertia pages.
            // The Razorpay submission POST lives in routes/donation.php
            // under /api/v1 (separate group, below).
            Route::middleware('web')
                ->group(base_path('routes/donate.php'));

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

            // Donation flow (Phase 3 endpoints land here). The
            // `idempotency` middleware applies the SETEX dedupe path to
            // all mutating verbs. GETs bypass naturally. Doctrine:
            // HTTP and async are separate concerns; one stuck middleware
            // does not take down donation intake.
            Route::middleware(['api', 'idempotency'])
                ->prefix('api/v1')
                ->group(base_path('routes/donation.php'));

            // Webhook flow (Phase 3 webhook controllers land here).
            // The `webhook-dedupe` middleware applies the SETEX dedupe
            // path BEFORE signature verification. Doctrine: cheaper
            // check first; expensive HMAC second.
            Route::middleware(['api', 'webhook-dedupe'])
                ->prefix('api/v1/webhooks')
                ->group(base_path('routes/webhook.php'));
        });
    }
}