<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignsModule;
use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Campaigns\Infrastructure\Repositories\EloquentCampaignRepository;
use App\Campaigns\Services\CampaignsQueryService;
use App\Persistence\Contracts\RepositoryRegistryContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Binding smoke tests for the Campaigns kernel.
 *
 * Mirrors the pattern at tests/Feature/Cms/CmsBindingsTest.php: assert
 * container resolution for each contract, verify the repository
 * registry has the kernel's entity-type entry, and confirm the module
 * declaration resolves.
 *
 * Doctrine: one binding per interface, owned by CampaignsServiceProvider.
 * These tests fail loudly if a future change removes the binding.
 */
final class CampaignsBindingsTest extends TestCase
{
    #[Test]
    public function testCampaignRepositoryContractResolvesToEloquent(): void
    {
        $resolved = $this->app->make(CampaignRepositoryContract::class);
        $this->assertInstanceOf(EloquentCampaignRepository::class, $resolved);
    }

    #[Test]
    public function testCampaignsQueryContractResolvesToService(): void
    {
        $resolved = $this->app->make(CampaignsQueryContract::class);
        $this->assertInstanceOf(CampaignsQueryService::class, $resolved);
    }

    #[Test]
    public function testRepositoryRegistryHasCampaignEntity(): void
    {
        /** @var RepositoryRegistryContract $registry */
        $registry = $this->app->make(RepositoryRegistryContract::class);
        $this->assertTrue(
            $registry->has('campaign'),
            'Registry missing entry for campaign'
        );
        $this->assertSame(
            EloquentCampaignRepository::class,
            $registry->resolve('campaign'),
        );
    }

    #[Test]
    public function testModuleDeclarationResolvesAndNames(): void
    {
        $module = $this->app->make(CampaignsModule::class);
        $this->assertInstanceOf(CampaignsModule::class, $module);
        $this->assertSame('campaigns', $module->name());
    }

    #[Test]
    public function testDependenciesIncludeShared(): void
    {
        $module = $this->app->make(CampaignsModule::class);
        $deps = $module->dependencies();
        $this->assertContains(\App\Shared\Contracts\ModuleContract::class, $deps);
    }
}
