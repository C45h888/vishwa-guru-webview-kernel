<?php

declare(strict_types=1);

namespace Tests\Feature\Gallery\Infrastructure;

use App\Gallery\Infrastructure\Repositories\EloquentGalleryRepository;
use DateTimeImmutable;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Integration tests for EloquentGalleryRepository.
 *
 * Repository-level invariants tested here:
 *   - filter on state = 'published' AND deleted_at IS NULL
 *   - LEFT JOIN image_count surfaces only published images
 *   - honor limit + offset for listDisplayable
 *   - listFeatured respects is_featured + limit
 */
final class EloquentGalleryRepositoryTest extends InfrastructureTestCase
{
    private EloquentGalleryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentGalleryRepository($this->adapter);
    }

    public function testFindByIdReturnsDetailForPublishedGallery(): void
    {
        $this->seedGallery(id: 'gallery_01HPUB', state: 'published', title: 'Pub');
        $detail = $this->repo->findById('gallery_01HPUB');

        $this->assertNotNull($detail);
        $this->assertSame('gallery_01HPUB', $detail->id);
        $this->assertSame('published', $detail->state);
        $this->assertSame([], $detail->images);
    }

    public function testFindByIdReturnsNullForDraftOrSoftDeleted(): void
    {
        $this->seedGallery(id: 'gallery_01HDRAFT', state: 'draft', title: 'Draft');
        $this->seedGallery(
            id: 'gallery_01HSOFTDEL',
            state: 'published',
            title: 'SoftDeleted',
            deletedAt: '2026-07-01T00:00:00+00:00',
        );
        $this->assertNull($this->repo->findById('gallery_01HDRAFT'));
        $this->assertNull($this->repo->findById('gallery_01HSOFTDEL'));
    }

    public function testListDisplayableExcludesNonPublishedAndSoftDeleted(): void
    {
        $this->seedGallery(id: 'gallery_01HA', state: 'published', title: 'A');
        $this->seedGallery(id: 'gallery_01HB', state: 'published', title: 'B');
        $this->seedGallery(id: 'gallery_01HC', state: 'draft', title: 'C');
        $this->seedGallery(
            id: 'gallery_01HD',
            state: 'published',
            title: 'D',
            deletedAt: '2026-07-01T00:00:00+00:00',
        );

        $result = $this->repo->listDisplayable(12, 0);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['items']);
    }

    public function testListDisplayableRespectsPagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedGallery(id: 'gallery_01HG'.$i, state: 'published', title: 'G'.$i);
        }

        $page1 = $this->repo->listDisplayable(2, 0);
        $this->assertCount(2, $page1['items']);
        $this->assertSame(5, $page1['total']);

        $page3 = $this->repo->listDisplayable(2, 4);
        $this->assertCount(1, $page3['items']);
        $this->assertSame(5, $page3['total']);
    }

    public function testListDisplayableIncludesImageCount(): void
    {
        $this->seedGallery(id: 'gallery_01HIMGS', state: 'published', title: 'With Images');
        $this->seedGalleryImage(galleryId: 'gallery_01HIMGS', state: 'published', displayOrder: 1);
        $this->seedGalleryImage(galleryId: 'gallery_01HIMGS', state: 'published', displayOrder: 2);
        $this->seedGalleryImage(galleryId: 'gallery_01HIMGS', state: 'draft', displayOrder: 3); // excluded
        $this->seedGallery(id: 'gallery_01HNOIMG', state: 'published', title: 'No Images');

        $result = $this->repo->listDisplayable(12, 0);
        $byId = [];
        foreach ($result['items'] as $summary) {
            $byId[$summary->id] = $summary;
        }
        $this->assertSame(2, $byId['gallery_01HIMGS']->imageCount);
        $this->assertSame(0, $byId['gallery_01HNOIMG']->imageCount);
    }

    public function testListFeaturedRespectsLimitAndFeaturedFlag(): void
    {
        $this->seedGallery(id: 'gallery_01HFA', state: 'published', title: 'A', displayOrder: 0, isFeatured: true);
        $this->seedGallery(id: 'gallery_01HFB', state: 'published', title: 'B', displayOrder: 1, isFeatured: true);
        $this->seedGallery(id: 'gallery_01HFC', state: 'published', title: 'C', displayOrder: 2, isFeatured: false);

        $featured = $this->repo->listFeatured(6);
        $this->assertCount(2, $featured);
    }

    public function testDetailImagesAreOrderedByDisplayOrder(): void
    {
        $this->seedGallery(id: 'gallery_01HORDER', state: 'published', title: 'Order');
        $this->seedGalleryImage(id: 'image_01HC', galleryId: 'gallery_01HORDER', state: 'published', displayOrder: 3);
        $this->seedGalleryImage(id: 'image_01HA', galleryId: 'gallery_01HORDER', state: 'published', displayOrder: 1);
        $this->seedGalleryImage(id: 'image_01HB', galleryId: 'gallery_01HORDER', state: 'published', displayOrder: 2);

        $detail = $this->repo->findById('gallery_01HORDER');
        $this->assertNotNull($detail);
        $this->assertCount(3, $detail->images);
        $this->assertSame('image_01HA', $detail->images[0]->id);
        $this->assertSame('image_01HB', $detail->images[1]->id);
        $this->assertSame('image_01HC', $detail->images[2]->id);
    }

    public function testDetailExcludesDraftAndSoftDeletedImages(): void
    {
        $this->seedGallery(id: 'gallery_01HMIX', state: 'published', title: 'Mixed');
        $this->seedGalleryImage(id: 'image_01HPUB', galleryId: 'gallery_01HMIX', state: 'published');
        $this->seedGalleryImage(id: 'image_01HDRFT', galleryId: 'gallery_01HMIX', state: 'draft');
        $this->seedGalleryImage(
            id: 'image_01HSOFTDEL',
            galleryId: 'gallery_01HMIX',
            state: 'published',
            deletedAt: '2026-07-01T00:00:00+00:00',
        );

        $detail = $this->repo->findById('gallery_01HMIX');
        $this->assertNotNull($detail);
        $this->assertCount(1, $detail->images);
        $this->assertSame('image_01HPUB', $detail->images[0]->id);
    }

    private function seedGallery(
        string $id,
        string $state,
        string $title,
        int $displayOrder = 0,
        bool $isFeatured = false,
        ?string $deletedAt = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO galleries (
                id, slug, title, state, display_order, is_featured,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :state, :dorder, :featured,
                \'{}\', :created, :updated, :deleted
            )',
            [
                'id' => $id,
                'slug' => strtolower($id),
                'title' => $title,
                'state' => $state,
                'dorder' => $displayOrder,
                'featured' => $isFeatured ? 1 : 0,
                'created' => $now,
                'updated' => $now,
                'deleted' => $deletedAt,
            ],
        );
    }

    private function seedGalleryImage(
        string $galleryId,
        string $state,
        int $displayOrder = 0,
        ?string $deletedAt = null,
        ?string $id = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $imageId = $id ?? 'image_'.bin2hex(random_bytes(4));
        $this->adapter->execute(
            'INSERT INTO gallery_images (
                id, gallery_id, state, display_order, is_featured,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :gid, :state, :dorder, 0,
                \'{}\', :created, :updated, :deleted
            )',
            [
                'id' => $imageId,
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
