<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Cms\Contracts\ImageUrlResolverContract;
use App\Cms\Domain\Entities\ContactInformation;
use App\Cms\Domain\Entities\HeroBanner;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\Entities\StaticPageReference;
use App\Cms\Domain\Repositories\ContactInformationRepositoryContract;
use App\Cms\Domain\Repositories\HeroBannerRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageReferenceRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Infrastructure\Repositories\EloquentContactInformationRepository;
use App\Cms\Infrastructure\Repositories\EloquentHeroBannerRepository;
use App\Cms\Infrastructure\Repositories\EloquentStaticPageReferenceRepository;
use App\Cms\Infrastructure\Repositories\EloquentStaticPageRepository;
use App\Cms\Infrastructure\UrlResolution\PublicMediaUrlResolver;
use App\Cms\Services\ReferenceResolutionService;
use App\Persistence\Contracts\RepositoryRegistryContract;
use App\Persistence\ValueObjects\EntityId;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Binding smoke tests for the CMS kernel.
 *
 * Mirrors the pattern at tests/Feature/SharedBindingsTest.php: assert
 * container resolution for each contract, and verify the repository
 * registry has all four kernel ENTITY_TYPE entries.
 *
 * Doctrine: one binding per interface, owned by CmsServiceProvider
 * (not by the kernel's domain providers). These tests fail loudly if
 * a future change removes the binding.
 */
final class CmsBindingsTest extends TestCase
{
    #[Test]
    public function testRepositoryContractsResolveToEloquentImplementations(): void
    {
        $bindings = [
            StaticPageRepositoryContract::class          => EloquentStaticPageRepository::class,
            HeroBannerRepositoryContract::class          => EloquentHeroBannerRepository::class,
            StaticPageReferenceRepositoryContract::class => EloquentStaticPageReferenceRepository::class,
            ContactInformationRepositoryContract::class  => EloquentContactInformationRepository::class,
        ];

        foreach ($bindings as $contract => $impl) {
            $resolved = $this->app->make($contract);
            $this->assertInstanceOf($impl, $resolved, "{$contract} must resolve to {$impl}");
        }
    }

    #[Test]
    public function testRepositoryRegistryHasAllFourEntries(): void
    {
        /** @var RepositoryRegistryContract $registry */
        $registry = $this->app->make(RepositoryRegistryContract::class);

        $this->assertTrue(
            $registry->has(StaticPage::ENTITY_TYPE),
            'Registry missing entry for '.StaticPage::ENTITY_TYPE
        );
        $this->assertTrue(
            $registry->has(HeroBanner::ENTITY_TYPE),
            'Registry missing entry for '.HeroBanner::ENTITY_TYPE
        );
        $this->assertTrue(
            $registry->has(StaticPageReference::ENTITY_TYPE),
            'Registry missing entry for '.StaticPageReference::ENTITY_TYPE
        );
        $this->assertTrue(
            $registry->has(ContactInformation::ENTITY_TYPE),
            'Registry missing entry for '.ContactInformation::ENTITY_TYPE
        );
    }

    #[Test]
    public function testImageUrlResolverResolvesToCmsMediaRoute(): void
    {
        /** @var ImageUrlResolverContract $resolver */
        $resolver = $this->app->make(ImageUrlResolverContract::class);

        $this->assertInstanceOf(PublicMediaUrlResolver::class, $resolver);

        // EntityId::fromString requires a strict 26-char ULID suffix,
        // so generate a real one rather than hardcoding a fake.
        $fileId = EntityId::generate('file_asset');
        $url = $resolver->resolve($fileId);
        $this->assertStringEndsWith("/media/{$fileId->value()}", $url);
    }

    #[Test]
    public function testReferenceResolutionServiceResolves(): void
    {
        // Smoke check: the service container constructs the service
        // successfully. ReferenceResolutionService constructor-injects
        // CampaignQueryContract — if the binding is broken, this fails
        // before any method runs.
        $service = $this->app->make(ReferenceResolutionService::class);
        $this->assertInstanceOf(ReferenceResolutionService::class, $service);
    }
}
