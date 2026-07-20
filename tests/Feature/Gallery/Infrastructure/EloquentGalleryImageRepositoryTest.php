<?php

declare(strict_types=1);

namespace Tests\Feature\Gallery\Infrastructure;

use App\Gallery\Infrastructure\Repositories\EloquentGalleryImageRepository;
use DateTimeImmutable;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Integration tests for EloquentGalleryImageRepository.
 *
 * Repository-level invariants tested here:
 *   - filter on state = 'published' AND deleted_at IS NULL
 *   - ordered by display_order ASC, id ASC
 *   - limit honored when provided
 */
final class EloquentGalleryImageRepositoryTest extends InfrastructureTestCase
{
    private EloquentGalleryImageRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentGalleryImageRepository($this->adapter);
    }

    public function testListByGalleryReturnsPublishedImagesOrdered(): void
    {
        $this->seedGallery();
        $this->seedImage(galleryId: 'gallery_01HG', state: 'published', displayOrder: 3);
        $this->seedImage(galleryId: 'gallery_01HG', state: 'published', displayOrder: 1);
        $this->seedImage(galleryId: 'gallery_01HG', state: 'published', displayOrder: 2);

        $images = $this->repo->listByGallery('gallery_01HG', null);
        $this->assertCount(3, $images);
        $this->assertSame(1, $images[0]->displayOrder);
        $this->assertSame(2, $images[1]->displayOrder);
        $this->assertSame(3, $images[2]->displayOrder);
    }

    public function testListByGalleryExcludesSoftDeleted(): void
    {
        $this->seedGallery();
        $this->seedImage(galleryId: 'gallery_01HG', state: 'published');
        $this->seedImage(
            galleryId: 'gallery_01HG',
            state: 'published',
            deletedAt: '2026-07-01T00:00:00+00:00',
        );

        $images = $this->repo->listByGallery('gallery_01HG', null);
        $this->assertCount(1, $images);
    }

    public function testListByGalleryExcludesDraftImages(): void
    {
        $this->seedGallery();
        $this->seedImage(galleryId: 'gallery_01HG', state: 'published');
        $this->seedImage(galleryId: 'gallery_01HG', state: 'draft');

        $images = $this->repo->listByGallery('gallery_01HG', null);
        $this->assertCount(1, $images);
    }

    public function testListByGalleryRespectsLimit(): void
    {
        $this->seedGallery();
        for ($i = 0; $i < 5; $i++) {
            $this->seedImage(galleryId: 'gallery_01HG', state: 'published', displayOrder: $i);
        }

        $images = $this->repo->listByGallery('gallery_01HG', 2);
        $this->assertCount(2, $images);
    }

    public function testListByGalleryReturnsEmptyForUnknownGallery(): void
    {
        $images = $this->repo->listByGallery('gallery_01HNONEXISTENT', null);
        $this->assertSame([], $images);
    }

    private function seedGallery(): void
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO galleries (
                id, slug, title, state, display_order, is_featured,
                metadata, created_at, updated_at
            ) VALUES (
                :id, :slug, :title, :state, 0, 0,
                \'{}\', :created, :updated
            )',
            [
                'id' => 'gallery_01HG',
                'slug' => 'gallery-01hg',
                'title' => 'Test Gallery',
                'state' => 'published',
                'created' => $now,
                'updated' => $now,
            ],
        );
    }

    private function seedImage(
        string $galleryId,
        string $state,
        int $displayOrder = 0,
        ?string $deletedAt = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO gallery_images (
                id, gallery_id, state, display_order, is_featured,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :gid, :state, :dorder, 0,
                \'{}\', :created, :updated, :deleted
            )',
            [
                'id' => 'image_'.bin2hex(random_bytes(6)),
                'gid' => $galleryId,
                'state' => $state,
                'dorder' => $displayOrder,
                'created' => $now,
                'updated' => $now,
                'deleted' => $deletedAt,
            ],
        );
    }
}
