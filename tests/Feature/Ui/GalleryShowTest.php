<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /gallery/{slug} (gallery.Show).
 *
 * Asserts the detail payload includes the images array carried by
 * GalleryDetailDTO and 404s on missing slugs.
 */
final class GalleryShowTest extends InfrastructureTestCase
{
    public function testShowRendersGalleryDetailWithImages(): void
    {
        $this->seedGallery(id: 'gal_show_1', title: 'Test Gallery');
        $this->seedGalleryImage(id: 'img_show_1', galleryId: 'gal_show_1');
        $this->seedGalleryImage(id: 'img_show_2', galleryId: 'gal_show_1');

        $response = $this->get('/gallery/gal_show_1');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('gallery/Show')
            ->where('gallery.slug', 'gal_show_1')
            ->where('gallery.title', 'Test Gallery')
            ->has('gallery.images', 2)
            ->etc()
        );
    }

    public function testShowReturns404ForMissingSlug(): void
    {
        $response = $this->get('/gallery/does-not-exist');

        $response->assertNotFound();
    }

    private function seedGallery(
        string $id,
        string $title,
        ?string $deletedAt = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO galleries (
                id, slug, title, state, is_featured, display_order,
                published_at, metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :state, 0, 0,
                :now, \'{}\', :now, :now, :deleted
            )',
            [
                'id' => $id,
                'slug' => strtolower($id),
                'title' => $title,
                'state' => 'published',
                'now' => $now,
                'deleted' => $deletedAt,
            ],
        );
    }

    private function seedGalleryImage(string $id, string $galleryId): void
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO gallery_images (
                id, gallery_id, display_order, is_featured, state,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :gid, 0, 0, :state,
                \'{}\', :now, :now, NULL
            )',
            [
                'id' => $id,
                'gid' => $galleryId,
                'state' => 'published',
                'now' => $now,
            ],
        );
    }
}
