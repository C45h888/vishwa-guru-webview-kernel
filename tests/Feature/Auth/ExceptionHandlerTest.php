<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PDOException;
use Tests\TestCase;

/**
 * ExceptionHandlerTest — App\Exceptions\Handler overrides the default
 * Laravel behaviour so the SPA's Inertia requests show a friendly
 * error page instead of a stack trace.
 *
 * Doctrine (Pass B polish):
 *   - Inertia XHR requests with an HTTP-style exception (404, 403,
 *     500) should render an Inertia error page.
 *   - QueryException wrapping a connection failure should render
 *     a 503 "service unavailable" page (NOT a 500), because a DB
 *     outage is a transient ops issue, not a code bug.
 *   - Non-Inertia requests should fall through to the default
 *     Laravel behaviour (Blade views or JSON envelope).
 */
final class ExceptionHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_inertia_404_renders_inertia_error_page(): void
    {
        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->get('/this-route-does-not-exist');

        $response->assertNotFound();
        $body = (string) $response->getContent();
        // The Inertia JSON payload has the component's slug as
        // `errors\/404` (forward-slash escaped per JSON spec).
        $this->assertStringContainsString('errors\\/404', $body);
        // The handler's stable message for status 404.
        $this->assertStringContainsString('could not be found', $body);
    }

    public function test_non_inertia_404_returns_default_404(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound();
    }

    public function test_database_connection_failure_renders_503(): void
    {
        $pdoException = new PDOException('SQLSTATE[08006]: could not translate host name', 0);
        $pdoException->errorInfo = ['08006', 7, ''];
        $queryException = new QueryException(
            'pgsql',
            'select * from "users" limit 1',
            [],
            $pdoException,
        );

        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        $request = Request::create('/admin', 'GET');
        $request->headers->set('X-Inertia', 'true');

        $response = $handler->render($request, $queryException);

        $this->assertSame(503, $response->getStatusCode());
    }

    public function test_non_connection_query_exception_renders_500(): void
    {
        $pdoException = new PDOException('Some other DB error', 0);
        $pdoException->errorInfo = ['23505', 7, ''];
        $queryException = new QueryException(
            'pgsql',
            'insert into "users"',
            [],
            $pdoException,
        );

        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);

        $request = Request::create('/admin', 'GET');

        $response = $handler->render($request, $queryException);
        $this->assertSame(500, $response->getStatusCode());
    }
}
