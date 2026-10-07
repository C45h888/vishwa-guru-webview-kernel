<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * HandleInertiaRequests — Inertia root view + shared props for every page.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - `authUser` is the canonical Inertia shared prop. It carries enough
 *     identity for any Svelte page to make role-aware UI decisions
 *     (e.g. hiding "admin" links) without re-fetching. Public pages
 *     see `null`; admin pages see the full row minus secrets.
 *   - The prop shape is stable: { id, name, email, role }. Adding new
 *     fields requires updating both this method AND the Svelte type
 *     definitions in `$shared/lib/inertia.ts` (Pass 2/3 will do this).
 *   - This middleware is the SINGLE place that decides what crosses
 *     the auth boundary into the SPA. Services never re-emit auth
 *     data — they trust this prop.
 */
final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'appName' => (string) config('app.name'),
            // Short brand identifier used in the public site header so the
            // long legal name stays available in body copy, footers, and
            // receipts. Doctrine: keep the full name authoritative in
            // config('app.name'); only the header chrome reads the short
            // form. The two are decoupled so a future rebrand of the
            // header does not require touching receipt templates.
            'appShortName' => (string) config('app.short_name'),
            'appUrl' => (string) config('app.url'),
            // Public trust identity (config/trust.php) — single source for
            // the footer, header and contact page social/email links.
            'trust' => [
                'email' => (string) config('trust.email'),
                'instagramUrl' => (string) config('trust.instagram_url'),
            ],
            // Phase 4: Admin Kernel — explicit CSRF token for Inertia forms.
            // Inertia's client normally reads this from the <meta name="csrf-token">
            // tag, but surfacing it via the shared-prop lets form components
            // include it explicitly when they need to (Login form, etc.)
            // without depending on a DOM scrape.
            'csrf_token' => csrf_token(),
            'authUser' => $user === null ? null : [
                'id' => $user->getKey(),
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'role' => (string) $user->role,
            ],
            // Razorpay environment flag (live|test). The webview uses
            // this to gate "Test mode" copy in the donate flow. Drives
            // off env so a single flip switches the public surface.
            'razorpayMode' => env('RAZORPAY_MODE', 'live') === 'test'
                ? 'test'
                : 'live',
        ]);
    }
}
