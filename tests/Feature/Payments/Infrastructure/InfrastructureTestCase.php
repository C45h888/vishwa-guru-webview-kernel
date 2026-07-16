<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Persistence\Infrastructure\LaravelDbAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base for all persistence integration tests.
 *
 * RefreshDatabase runs the SQLite migration (2026_07_16_000002) before
 * each test, giving each test a clean schema-backed in-memory DB.
 * The adapter delegates to whatever DB connection Laravel resolves —
 * phpunit.xml sets DB_CONNECTION=sqlite + DB_DATABASE=:memory:,
 * so this is the SQLite in-memory adapter for all tests.
 */
abstract class InfrastructureTestCase extends TestCase
{
    use RefreshDatabase;

    protected LaravelDbAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the default connection resolved by Laravel.
        // In tests this is the sqlite in-memory connection;
        // in production it would be pgsql (Neon).
        $this->adapter = new LaravelDbAdapter(
            \Illuminate\Support\Facades\DB::connection(),
        );
    }
}
