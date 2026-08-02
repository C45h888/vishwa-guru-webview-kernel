<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureUserIsAdmin — gates /admin/* behind the canonical admin role.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - Distinct from Laravel's `auth` middleware. That middleware answers
 *     "are you logged in?" and redirects to /login if not. This middleware
 *     answers "are you allowed into the admin surface?" and returns 403
 *     otherwise. The two concerns are split because /login's "redirect
 *     anonymous to login" behaviour must NOT swallow the 403 case — if
 *     a non-admin user is logged in and hits /admin, we want them to see
 *     a 403 page, not get bounced to /login (which they can already see).
 *   - The role check delegates to `User::isAdmin()` so the model owns the
 *     role predicate. Adding a new role later is a model change, not a
 *     middleware change.
 *   - This middleware runs AFTER `auth` in the route group, so by the
 *     time we look up `$request->user()` it is non-null. The null check
 *     is defensive belt-and-braces.
 */
final class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}
