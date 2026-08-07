<?php

declare(strict_types=1);

namespace App\Providers;

use App\Runtime\Http\Controllers\HealthController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * Also configures rate limiters consumed by `ThrottleRequests::':NAME'`.
     * Without an explicit named limiter, the kernel falls through to
     * `(int) $name` which casts 'api' → 0 and blocks EVERY request
     * (X-RateLimit-Limit: 0). Wave 1A investigation finding A.
     */
    public function boot(): void
    {
        $this->configureRateLimiter();

        $this->routes(function () {
            // Public site (Phase 3+) — web middleware group.
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // Phase 4: Admin auth surface (login + logout only).
            // Mounted under the web group so it shares session/CSRF
            // with the public site, but its routes are guarded by
            // `guest` (login) and `auth` (logout) middleware inside
            // the file itself.
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));

            // Phase 4: Admin kernel surface (/admin/*).
            // Triple-middleware pipeline is applied inside the file:
            //   web    — session, CSRF, cookies
            //   auth   — must be logged in
            //   admin  — must satisfy User::isAdmin()
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

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
            // NOTE: `api` middleware group has `ThrottleRequests::':api'`
            // — requires a named limiter registered via configureRateLimiter()
            // below, otherwise the kernel falls through to (int)'api' = 0
            // and blocks every request immediately.
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

    /**
     * Register the named rate limiters consumed by the `api` middleware group.
     *
     * Without an entry here, `ThrottleRequests::':api'` falls through to the
     * numeric path and casts `api` → `0`, producing X-RateLimit-Limit: 0 on
     * every request (causing immediate 429s). The 'api' named limiter covers
     * all /api/v1/* routes that don't apply their own throttle. A separate
     * 'checkout' limiter is reserved for the donation POST once it grows its
     * own quota needs; today it inherits 'api'.
     *
     * Tuning rationale:
     * - 120/min per (user|IP) for the default 'api' limiter — generous enough
     *   for dev/test exploration (the user is iterating against Razorpay's
     *   test mode keys), strict enough that a runaway curl loop self-throttles.
     * - Production may want to dial down once the abuse profile is known.
     */
    protected function configureRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Per-user when authenticated, per-IP for the public surface.
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(120)->by((string) $key);
        });

        // Placeholder for a future dedicated checkout limiter. Wired today
        // so a one-line tuning change is enough to detach the donation flow
        // from the generic 'api' bucket later.
        RateLimiter::for('checkout', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(120)->by((string) $key);
        });
    }
}
