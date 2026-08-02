<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        HandleCors::class,
        ValidatePostSize::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            ThrottleRequests::class.':api',
            SubstituteBindings::class,
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => Authenticate::class,
        'throttle' => ThrottleRequests::class,
        // Phase 4: Admin Kernel — guest guard for /login. Mirrors Laravel
        // canonical RedirectIfAuthenticated; if a logged-in admin hits
        // /login they're sent straight to /admin instead of seeing the
        // form again.
        // @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/app/Http/Middleware/RedirectIfAuthenticated.php
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        // NOTE: The `idempotency` alias here is the MIDDLEWARE alias — distinct
        // from `idempotency_key`, which is the per-request DB column /
        // DonationIntent payload key (App\Payments\Infrastructure\Repositories
        // \IdempotencyKeyRepository). The two are unrelated concepts.
        'idempotency' => \App\Http\Middleware\IdempotencyMiddleware::class,
        'webhook-dedupe' => \App\Http\Middleware\WebhookDedupeMiddleware::class,
        // Phase 4: Admin Kernel — role gate for /admin/* routes.
        // Distinct from `auth` (which only checks "logged in"); this
        // middleware additionally checks `User::isAdmin()`.
        // @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/app/Http/Middleware/EnsureUserIsAdmin.php
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    ];
}
