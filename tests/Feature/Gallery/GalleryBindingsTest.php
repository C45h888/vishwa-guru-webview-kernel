<?php

declare(strict_types=1);

namespace Tests\Feature\Gallery;

use App\Gallery\Contracts\GalleryQueryContract;
use App\Gallery\Domain\Repositories\GalleryImageRepositoryContract;
use App\Gallery\Domain\Repositories\GalleryRepositoryContract;
use App\Gallery\GalleryModule;
use App\Gallery\Infrastructure\Repositories\EloquentGalleryImageRepository;
use App\Gallery\Infrastructure\Repositories\EloquentGalleryRepository;
use App\Gallery\Services\GalleryQueryService;
use App\Persistence\Contracts\RepositoryRegistryContract;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Binding smoke tests for the Gallery kernel.
 *
 * Mirrors tests/Feature/Cms/CmsBindingsTest.php and
 * tests/Feature/Campaigns/CampaignsBindingsTest.php.
 */
final class GalleryBindingsTest extends TestCase
{
    #[Test]
    public function testGalleryRepositoryContractResolvesToEloquent(): void
    {
        $resolved = $this->app->make(GalleryRepositoryContract::class);
        $this->assertInstanceOf(EloquentGalleryRepository::class, $resolved);
    }

    #[Test]
    public function testGalleryImageRepositoryContractResolvesToEloquent(): void
    {
        $resolved = $this->app->make(GalleryImageRepositoryContract::class);
        $this->assertInstanceOf(EloquentGalleryImageRepository::class, $resolved);
    }

    #[Test]
    public function testGalleryQueryContractResolvesToService(): void
    {
        $resolved = $this->app->make(GalleryQueryContract::class);
        $this->assertInstanceOf(GalleryQueryService::class, $resolved);
    }

    #[Test]
    public function testRepositoryRegistryHasGalleryAndGalleryImage(): void
    {
        /** @var RepositoryRegistryContract $registry */
        $registry = $this->app->make(RepositoryRegistryContract::class);
        $this->assertTrue($registry->has('gallery'), 'Registry missing entry for gallery');
        $this->assertSame(
            EloquentGalleryRepository::class,
            $registry->resolve('gallery'),
        );
        $this->assertTrue($registry->has('gallery_image'), 'Registry missing entry for gallery_image');
        $this->assertSame(
            EloquentGalleryImageRepository::class,
            $registry->resolve('gallery_image'),
        );
    }

    #[Test]
    public function testModuleDeclarationResolvesAndNames(): void
    {
        $module = $this->app->make(GalleryModule::class);
        $this->assertInstanceOf(GalleryModule::class, $module);
        $this->assertSame('gallery', $module->name());
    }
}
