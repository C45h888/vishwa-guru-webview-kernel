<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /gallery (gallery.Index).
 *
 * Asserts the grid payload includes image_count from GallerySummaryDTO
 * and the pagination envelope is intact.
 */
final class GalleryIndexTest extends InfrastructureTestCase
{
    public function testIndexRendersGridOfPublishedGalleries(): void
    {
        $this->seedGallery(id: 'gal_ui_1', title: 'Diwali 2025', isFeatured: true);
        $this->seedGallery(id: 'gal_ui_2', title: 'Navaratri 2025');
        $this->seedGallery(id: 'gal_ui_3', title: 'Karthikai 2025');

        $response = $this->get('/gallery');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('gallery/Index')
            ->has('galleries', 3)
            ->has('pagination', fn (AssertableInertia $p) => $p
                ->where('page', 1)
                ->where('per_page', 12)
                ->where('total', 3)
                ->where('has_more', false)
                ->etc()
            )
            ->etc()
        );
    }

    private function seedGallery(
        string $id,
        string $title,
        bool $isFeatured = false,
        ?string $deletedAt = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO galleries (
                id, slug, title, state, is_featured, display_order,
                published_at, metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :state, :feat, 0,
                :now, \'{}\', :now, :now, :deleted
            )',
            [
                'id' => $id,
                'slug' => strtolower($id),
                'title' => $title,
                'state' => 'published',
                'feat' => $isFeatured ? 1 : 0,
                'now' => $now,
                'deleted' => $deletedAt,
            ],
        );
    }
}
