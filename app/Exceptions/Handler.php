<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Handler — custom exception handler for the Temple Trust app.
 *
 * Doctrine (Pass B polish):
 *   - For Inertia XHR requests (`X-Inertia: true`), render an Inertia
 *     error page so the SPA can show a friendly message INSTEAD of a
 *     blank 500. The Inertia client handles the navigation back to
 *     the error page automatically.
 *   - For non-Inertia requests (browser refresh, direct URL hit),
 *     fall back to the standard Laravel error views at
 *     `resources/views/errors/{code}.blade.php`. We provide Blade
 *     fallbacks for 404/403/500/503 so direct hits don't break.
 *   - Database connection failures (PDOException) are wrapped in
 *     `QueryException`. We surface those as 503 ("Service temporarily
 *     unavailable") with a human-friendly explanation. Doctrine: a
 *     Postgres outage is not a code bug; the user should see a calm
 *     message, not a stack trace.
 *   - The `previous` is suppressed in production for safety
 *     (matches Laravel's default behaviour).
 */
final class Handler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     *
     * This override ONLY adjusts the rendering path for the cases
     * where the user-facing surface needs Inertia. Everything else
     * delegates to the parent (which renders Blade views, Ignition,
     * or the default JSON envelope as appropriate).
     *
     * @param  Request  $request
     */
    public function render($request, Throwable $e): Response
    {
        // 1. Inertia requests: render an Inertia error page.
        if ($request->header('X-Inertia') && $this->shouldRenderInertiaError($e)) {
            return $this->renderInertiaError($request, $e);
        }

        // 2. Database connection failures (any request): map to 503
        // BEFORE the global handler renders them as 500. This is the
        // single most common production failure mode — the user sees
        // a transient outage message rather than a code-error.
        if ($e instanceof QueryException && $this->isConnectionFailure($e)) {
            return $this->renderServiceUnavailable($request, $e);
        }

        // 3. Anything else: defer to the parent (Blade / JSON / Ignition).
        return parent::render($request, $e);
    }

    /**
     * Render an Inertia error page for an XHR request.
     */
    private function renderInertiaError(Request $request, Throwable $e): Response
    {
        $status = $this->resolveStatus($e);
        $props = $this->buildErrorProps($e, $status);

        return Inertia::render('errors/'.$status, $props)
            ->toResponse($request)
            ->setStatusCode($status);
    }

    /**
     * Render the 503 "Service temporarily unavailable" page for
     * database connection failures.
     */
    private function renderServiceUnavailable(Request $request, Throwable $e): Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::render('errors/503', [
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
                'authUser' => $this->currentAuthUser($request),
                'reason' => 'database_unavailable',
                'retry_after' => 30,
            ])->toResponse($request)->setStatusCode(503);
        }

        // Non-Inertia fallback: render the 503 view if present, else
        // a minimal plain-text body.
        $view = $this->findView(503);
        if ($view !== null) {
            return response()->view($view, [
                'reason' => 'database_unavailable',
            ], 503);
        }

        return response(
            'Service temporarily unavailable. The database is unreachable. Please try again in a moment.',
            503,
        );
    }

    private function shouldRenderInertiaError(Throwable $e): bool
    {
        // Render Inertia for HttpException (404, 403, 500, 503, etc.)
        // and for runtime errors that map to a status code.
        return $e instanceof HttpExceptionInterface
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException;
    }

    private function resolveStatus(Throwable $e): int
    {
        if ($e instanceof AuthenticationException) {
            return 401;
        }
        if ($e instanceof AuthorizationException) {
            return 403;
        }
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Build the props surface for the Inertia error page.
     *
     * @return array<string, mixed>
     */
    private function buildErrorProps(Throwable $e, int $status): array
    {
        $props = [
            'appName' => config('app.name'),
            'appUrl' => config('app.url'),
            'status' => $status,
            'message' => $this->messageFor($e, $status),
            'authUser' => null, // set by the per-request render path
        ];

        // QueryException for connection failures already renders as 503
        // via renderServiceUnavailable, so we don't reach this branch for
        // them. But if a non-connection QueryException reaches here, we
        // still surface it as 500 with a generic message.

        return $props;
    }

    /**
     * Per-request auth user payload, matching the HandleInertiaRequests
     * shape so the error page can render the header consistently.
     *
     * @return array<string, mixed>|null
     */
    private function currentAuthUser(Request $request): ?array
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => (string) $user->role,
        ];
    }

    private function messageFor(Throwable $e, int $status): string
    {
        return match ($status) {
            401 => 'You need to sign in to access this page.',
            403 => 'You do not have permission to access this page.',
            404 => 'The page you were looking for could not be found.',
            503 => 'The service is temporarily unavailable. Please try again in a moment.',
            default => 'An unexpected error occurred. The admin team has been notified.',
        };
    }

    private function isConnectionFailure(QueryException $e): bool
    {
        // SQLSTATE 08006 = connection failure, 08001 = unable to connect,
        // 08000 = connection exception. The driver message check is a
        // belt-and-braces fallback for environments where the SQLSTATE
        // is not the first failure indicator.
        $sqlState = $e->getCode();
        if (in_array($sqlState, ['08006', '08001', '08000'], true)) {
            return true;
        }

        $message = $e->getMessage();

        return str_contains($message, 'getaddrinfo')
            || str_contains($message, 'could not translate host name')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'server closed the connection unexpectedly')
            || str_contains($message, 'no connection to the server');
    }

    /**
     * Look up a Blade view for the given HTTP status code. Returns
     * null if the view doesn't exist, so the caller can decide.
     */
    private function findView(int $status): ?string
    {
        $candidates = [
            "errors::{$status}",
            "errors.{$status}",
        ];

        foreach ($candidates as $view) {
            if (view()->exists($view)) {
                return $view;
            }
        }

        return null;
    }
}
