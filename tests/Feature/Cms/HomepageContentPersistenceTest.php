<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Domain\ValueObjects\HomepageContent;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use App\Cms\Services\HomepageContentFactory;
use App\Cms\Domain\Entities\StaticPage;
use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * SQLite persistence + factory round-trip for the homepage_content aggregate.
 *
 * Proves the application boundary compensates for the intentionally
 * absent JSON foreign key:
 *   - With a known cms_media_assets row, the value object constructs.
 *   - After soft-deleting the media row, a subsequent write is rejected.
 */
final class HomepageContentPersistenceTest extends InfrastructureTestCase
{
    public function test_homepage_content_round_trips_via_repository(): void
    {
        if (PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('Requires PHP 8.4+ for sealed interface syntax in Block.php');
        }

        // Insert a cms_media_assets row.
        $mediaId = $this->insertMediaAsset('content_block_image', 'homepage-story');

        // Build a homepageContent via the factory.
        /** @var HomepageContentFactory $factory */
        $factory = $this->app->make(HomepageContentFactory::class);
        $content = $factory->fromArray($this->validPayload($mediaId));

        $this->assertInstanceOf(HomepageContent::class, $content);

        // Persist a static page carrying the content.
        /** @var StaticPageRepositoryContract $pages */
        $pages = $this->app->make(StaticPageRepositoryContract::class);
        $page = StaticPage::draft(
            slug: new PageSlug('home-persist'),
            title: 'Home Persist',
            metaDescription: 'm',
            body: new PageBody(version: 1, blocks: []),
            seoMetadata: new SeoMetadata(),
            isHomepage: true,
            displayOrder: 0,
            createdBy: 'test',
            homepageContent: $content,
        );
        $pages->save($page);

        // Reload + assert round-trip equality.
        $found = $pages->findBySlug(new PageSlug('home-persist'));
        $this->assertNotNull($found);
        $this->assertNotNull($found->homepageContent());
        $this->assertSame(
            $content->toArray(),
            $found->homepageContent()->toArray(),
        );
    }

    public function test_save_is_rejected_after_the_referenced_media_is_soft_deleted(): void
    {
        if (PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('Requires PHP 8.4+ for sealed interface syntax in Block.php');
        }

        $mediaId = $this->insertMediaAsset('content_block_image', 'homepage-story');

        /** @var HomepageContentFactory $factory */
        $factory = $this->app->make(HomepageContentFactory::class);
        $content = $factory->fromArray($this->validPayload($mediaId));

        // Soft-delete the cms_media_assets row.
        $this->adapter->execute(
            'UPDATE cms_media_assets SET deleted_at = :now, updated_at = :now WHERE id = :id',
            ['id' => $mediaId, 'now' => (new \DateTimeImmutable())->format(DATE_ATOM)],
        );

        // The factory must now reject the same payload.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in cms_media_assets');
        $factory->fromArray($this->validPayload($mediaId));
    }

    private function insertMediaAsset(string $mediaType, string $seed): string
    {
        $fileAssetId = 'file_asset_'.bin2hex(random_bytes(13));
        $mediaAssetId = 'cms_media_asset_'.bin2hex(random_bytes(13));

        $this->adapter->execute(
            'INSERT INTO file_assets (id, owner_type, owner_id, storage_disk, storage_path, mime_type, file_size_bytes, content_hash, created_at, updated_at) '
            ."VALUES (:id, 'static_page_attachment', 'homepage-test', 'local', :path, 'image/jpeg', 1024, :hash, :now, :now)",
            [
                'id' => $fileAssetId,
                'path' => 'homepage/'.$seed.'.jpg',
                'hash' => bin2hex(random_bytes(16)),
                'now' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ],
        );

        $this->adapter->execute(
            'INSERT INTO cms_media_assets (id, file_asset_id, media_type, state, alt_text, created_at, updated_at) '
            ."VALUES (:id, :file_id, :type, 'published', :alt, :now, :now)",
            [
                'id' => $mediaAssetId,
                'file_id' => $fileAssetId,
                'type' => $mediaType,
                'alt' => 'Test media',
                'now' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ],
        );

        return $mediaAssetId;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(?string $imageId = null): array
    {
        return [
            'version' => 1,
            'story' => [
                'eyebrow' => 'Our Story',
                'title' => 'A trust sustained by seva',
                'body' => 'Temple Trust is a registered charitable trust.',
                'cta_label' => 'Read more',
                'cta_url' => '/about',
                'image_file_id' => $imageId,
                'alt_text' => 'Temple and sacred grounds',
            ],
            'mission_quote' => [
                'eyebrow' => 'Our Mission',
                'quote' => 'To preserve the sacred traditions.',
                'attribution' => null,
            ],
            'programs' => [
                [
                    'key' => 'pooja',
                    'eyebrow' => 'Practice',
                    'title' => 'Daily Pooja',
                    'body' => 'The rhythm of pooja.',
                    'image_file_id' => null,
                    'alt_text' => 'Daily pooja',
                ],
                [
                    'key' => 'annadanam',
                    'eyebrow' => 'Service',
                    'title' => 'Annadanam',
                    'body' => 'Free meals served daily.',
                    'image_file_id' => null,
                    'alt_text' => 'Annadanam',
                ],
                [
                    'key' => 'temple_care',
                    'eyebrow' => 'Stewardship',
                    'title' => 'Temple Care',
                    'body' => 'The temple structure.',
                    'image_file_id' => null,
                    'alt_text' => 'Temple care',
                ],
            ],
            'trust_panel' => [
                'eyebrow' => 'Trust & Accountability',
                'title' => 'Stewardship',
                'registration' => 'Formally registered.',
                'tax_status' => 'Tax documentation available.',
                'operating_principles' => ['Service first'],
                'vows' => ['Preserve tradition'],
            ],
            'donate_cta' => [
                'eyebrow' => 'Offer Your Seva',
                'title' => 'Help sustain',
                'body' => 'Every offering supports daily worship.',
                'cta_label' => 'Donate Now',
                'cta_url' => '/donate',
            ],
        ];
    }
}
