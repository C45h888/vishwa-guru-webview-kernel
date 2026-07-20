<?php

declare(strict_types=1);

namespace Tests\Feature\Gallery;

use App\Gallery\Infrastructure\Repositories\EloquentGalleryRepository;
use App\Gallery\Services\GalleryQueryService;
use DateTimeImmutable;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Service-level tests for GalleryQueryService.
 *
 * Service-level invariants tested here:
 *   - perPage is clamped to [1, 50]
 *   - page < 1 is bumped to 1
 *   - findBySlug / findById delegate to repo (returns null on miss)
 */
final class GalleryQueryServiceTest extends InfrastructureTestCase
{
    private GalleryQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GalleryQueryService(
            new EloquentGalleryRepository($this->adapter),
        );
    }

    public function testListDisplayableEnforcesPerPageClampUpper(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->seedGallery(id: 'gallery_01HG'.$i, state: 'published', title: 'G'.$i);
        }
        $page = $this->service->listDisplayable(1, 200);
        $this->assertSame(50, $page->perPage);
    }

    public function testListDisplayableBumpsPageBelowOne(): void
    {
        $page = $this->service->listDisplayable(-5, 12);
        $this->assertSame(1, $page->page);
    }

    public function testFindBySlugReturnsNullForMissing(): void
    {
        $this->assertNull($this->service->findBySlug('nonexistent'));
    }

    public function testFindByIdReturnsDetailForPublished(): void
    {
        $this->seedGallery(id: 'gallery_01HPUB', state: 'published', title: 'Pub');
        $detail = $this->service->findById('gallery_01HPUB');
        $this->assertNotNull($detail);
        $this->assertSame('gallery_01HPUB', $detail->id);
    }

    public function testFindByIdReturnsNullForDraft(): void
    {
        $this->seedGallery(id: 'gallery_01HDRFT', state: 'draft', title: 'Draft');
        $this->assertNull($this->service->findById('gallery_01HDRFT'));
    }

    private function seedGallery(
        string $id,
        string $state,
        string $title,
        int $displayOrder = 0,
        ?string $deletedAt = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO galleries (
                id, slug, title, state, display_order, is_featured,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :state, :dorder, 0,
                \'{}\', :created, :updated, :deleted
            )',
            [
                'id' => $id,
                'slug' => strtolower($id),
                'title' => $title,
                'state' => $state,
                'dorder' => $displayOrder,
                'created' => $now,
                'updated' => $now,
                'deleted' => $deletedAt,
            ],
        );
    }
}
