<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AuthenticatedSessionController — login + logout for the canonical admin.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - Mirrors Laravel Breeze 1.x's Inertia stack `AuthenticatedSessionController`
 *     1:1 so a future Laravel 11 / Breeze 2.x upgrade is a swap, not a rewrite.
 *   - Login form renders the Svelte page at `resources/js/domains/Auth/Login.svelte`.
 *     The `status` prop carries session flash (e.g. "logged out").
 *   - `store()` calls `$request->authenticate()` (LoginRequest) which performs
 *     password verification AND rate-limit enforcement. Session regen happens
 *     immediately after a successful auth.
 *   - `destroy()` invalidates the session AND regenerates the CSRF token
 *     (Breeze canonical, prevents session-fixation after logout).
 *   - We DO NOT use the `home` route constant here; instead we redirect to
 *     `/admin` because the only authenticated surface is the admin kernel.
 *     The constant lives on RouteServiceProvider for future flexibility.
 *   - No role check in this controller — that lives in EnsureUserIsAdmin
 *     middleware. This controller answers the question "are you a valid user?";
 *     the middleware answers "are you allowed into /admin?".
 */
final class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
