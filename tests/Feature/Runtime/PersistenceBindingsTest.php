<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\Contracts\RepositoryRegistryContract;
use App\Persistence\Infrastructure\LaravelDbAdapter;
use App\Persistence\Infrastructure\RepositoryRegistry;
use App\Shared\Support\Result;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests verifying the PersistenceServiceProvider bindings resolve
 * to the expected contracts and concrete implementations.
 *
 * Mirrors the pattern of tests/Feature/SharedBindingsTest.php but
 * exercises the persistence contract end-to-end against the configured
 * DB connection (sqlite :memory: under phpunit.xml).
 */
final class PersistenceBindingsTest extends TestCase
{
    #[Test]
    public function it_resolves_the_persistence_adapter_to_a_laravel_db_adapter(): void
    {
        $adapter = $this->app->make(PersistenceAdapterContract::class);

        $this->assertInstanceOf(LaravelDbAdapter::class, $adapter);
        $this->assertInstanceOf(PersistenceAdapterContract::class, $adapter);
    }

    #[Test]
    public function it_reports_the_adapter_as_connected_against_sqlite_memory(): void
    {
        $adapter = $this->app->make(PersistenceAdapterContract::class);

        $this->assertTrue($adapter->isConnected());
    }

    #[Test]
    public function the_persistence_adapter_driver_is_sqlite_in_test_env(): void
    {
        $adapter = $this->app->make(PersistenceAdapterContract::class);

        $this->assertSame('sqlite', $adapter->driver());
    }

    #[Test]
    public function the_persistence_adapter_can_execute_a_select_one_query(): void
    {
        $adapter = $this->app->make(PersistenceAdapterContract::class);

        $result = $adapter->query('SELECT 1 AS one');

        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->isSuccess(), 'SELECT 1 should succeed against sqlite :memory:');

        $rows = $result->value();
        $this->assertCount(1, $rows);
        $this->assertSame(1, (int) $rows[0]['one']);
    }

    #[Test]
    public function it_resolves_the_repository_registry_to_the_in_memory_impl(): void
    {
        $registry = $this->app->make(RepositoryRegistryContract::class);

        $this->assertInstanceOf(RepositoryRegistry::class, $registry);
        $this->assertInstanceOf(RepositoryRegistryContract::class, $registry);
    }

    #[Test]
    public function the_repository_registry_register_resolve_round_trip(): void
    {
        $registry = $this->app->make(RepositoryRegistryContract::class);

        // Sentinel class-string — we don't need a real RepositoryContract impl
        // for this contract test; the registry just stores class strings.
        $registry->register('test_entity', \stdClass::class);

        $this->assertTrue($registry->has('test_entity'));
        $this->assertSame(\stdClass::class, $registry->resolve('test_entity'));
        $this->assertContains('test_entity', $registry->entityTypes());
    }

    #[Test]
    public function the_repository_registry_resolve_returns_null_for_unknown_entity(): void
    {
        $registry = $this->app->make(RepositoryRegistryContract::class);

        $this->assertNull($registry->resolve('nonexistent_entity'));
        $this->assertFalse($registry->has('nonexistent_entity'));
    }

    #[Test]
    public function the_persistence_adapter_returns_a_valid_identifier(): void
    {
        $adapter = $this->app->make(PersistenceAdapterContract::class);

        $identifier = $adapter->identifier();

        // Must be a valid ULID-backed Identifier (Identifier constructor validates).
        $this->assertNotEmpty($identifier->value());
        $this->assertSame(26, strlen($identifier->value()));
    }
}