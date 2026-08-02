<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * RedirectIfAuthenticated — `guest` middleware alias target.
 *
 * Mirrors Laravel 10's canonical `Illuminate\Auth\Middleware\RedirectIfAuthenticated`
 * exactly. We recreate it locally (rather than depending on the
 * framework class) so the Kernel alias registration stays explicit and
 * the file is searchable from the IDE.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - Used by `routes/auth.php` to wrap the login form. If a logged-in
 *     admin hits /login, they get sent straight to /admin instead of
 *     seeing a useless login form.
 *   - The redirect target is the admin dashboard. Pass 1 has no other
 *     post-login destination — there is no public-site "account" page
 *     because the public site has no user accounts (donors are
 *     anonymous at the auth layer; admins authenticate here).
 */
final class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            return redirect('/admin');
        }

        return $next($request);
    }
}
