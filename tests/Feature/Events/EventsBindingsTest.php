<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Events\Contracts\EventsQueryContract;
use App\Events\Domain\Repositories\EventRepositoryContract;
use App\Events\EventsModule;
use App\Events\Infrastructure\Repositories\EloquentEventRepository;
use App\Events\Services\EventsQueryService;
use App\Persistence\Contracts\RepositoryRegistryContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Binding smoke tests for the Events kernel.
 */
final class EventsBindingsTest extends TestCase
{
    #[Test]
    public function testEventRepositoryContractResolvesToEloquent(): void
    {
        $resolved = $this->app->make(EventRepositoryContract::class);
        $this->assertInstanceOf(EloquentEventRepository::class, $resolved);
    }

    #[Test]
    public function testEventsQueryContractResolvesToService(): void
    {
        $resolved = $this->app->make(EventsQueryContract::class);
        $this->assertInstanceOf(EventsQueryService::class, $resolved);
    }

    #[Test]
    public function testRepositoryRegistryHasEventEntity(): void
    {
        /** @var RepositoryRegistryContract $registry */
        $registry = $this->app->make(RepositoryRegistryContract::class);
        $this->assertTrue($registry->has('event'), 'Registry missing entry for event');
        $this->assertSame(
            EloquentEventRepository::class,
            $registry->resolve('event'),
        );
    }

    #[Test]
    public function testModuleDeclarationResolvesAndNames(): void
    {
        $module = $this->app->make(EventsModule::class);
        $this->assertInstanceOf(EventsModule::class, $module);
        $this->assertSame('events', $module->name());
    }
}
