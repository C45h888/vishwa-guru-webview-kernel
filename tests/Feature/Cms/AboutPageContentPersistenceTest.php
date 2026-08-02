<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\Repositories\CmsMediaAssetRepositoryContract;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\ValueObjects\AboutPageContent;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;
use App\Cms\Domain\ValueObjects\PageBody;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use App\Cms\Services\AboutPageContentFactory;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * SQLite persistence + factory round-trip for the about_page_content aggregate.
 *
 * Proves the application boundary compensates for the intentionally
 * absent JSON foreign key:
 *   - With a known cms_media_assets row, the value object constructs.
 *   - After soft-deleting the media row, a subsequent write is rejected.
 *
 * Mirrors HomepageContentPersistenceTest so the about-page surface
 * carries the same persistence guarantee.
 */
final class AboutPageContentPersistenceTest extends InfrastructureTestCase
{
    public function test_about_content_round_trips_via_repository(): void
    {
        if (PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('Requires PHP 8.4+ for sealed interface syntax in Block.php');
        }

        $imageId = $this->insertMediaAsset('content_block_image', 'about-values');
        $photoId = $this->insertMediaAsset('content_block_image', 'about-trustee');

        /** @var AboutPageContentFactory $factory */
        $factory = $this->app->make(AboutPageContentFactory::class);
        $content = $factory->fromArray($this->validPayload($imageId, $photoId));

        $this->assertInstanceOf(AboutPageContent::class, $content);

        /** @var StaticPageRepositoryContract $pages */
        $pages = $this->app->make(StaticPageRepositoryContract::class);
        $page = StaticPage::draft(
            slug: new PageSlug('about-persist'),
            title: 'About Persist',
            metaDescription: 'm',
            body: new PageBody(version: 1, blocks: []),
            seoMetadata: new SeoMetadata(),
            isHomepage: false,
            displayOrder: 10,
            createdBy: 'test',
            aboutPageContent: $content,
        );
        $pages->save($page);

        $found = $pages->findBySlug(new PageSlug('about-persist'));
        $this->assertNotNull($found);
        $this->assertNotNull($found->aboutPageContent());
        $this->assertSame(
            $content->toArray(),
            $found->aboutPageContent()->toArray(),
        );
    }

    public function test_save_is_rejected_after_the_referenced_media_is_soft_deleted(): void
    {
        if (PHP_VERSION_ID < 80400) {
            $this->markTestSkipped('Requires PHP 8.4+ for sealed interface syntax in Block.php');
        }

        $imageId = $this->insertMediaAsset('content_block_image', 'about-values');

        $payload = $this->validPayload($imageId, null);
        $payload['trustees'] = []; // drop the trustee that needs $photoId

        // First build is successful.
        /** @var AboutPageContentFactory $factory */
        $factory = $this->app->make(AboutPageContentFactory::class);
        $factory->fromArray($payload);

        // Soft-delete the cms_media_assets row.
        $this->adapter->execute(
            'UPDATE cms_media_assets SET deleted_at = :now, updated_at = :now WHERE id = :id',
            ['id' => $imageId, 'now' => (new \DateTimeImmutable())->format(DATE_ATOM)],
        );

        // The factory must now reject the same payload.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist in cms_media_assets');

        $factory->fromArray($payload);
    }

    private function insertMediaAsset(string $mediaType, string $seed): string
    {
        $fileAssetId = 'file_asset_'.bin2hex(random_bytes(13));
        $mediaAssetId = 'cms_media_'.bin2hex(random_bytes(13));

        /** @var FileAssetRepositoryContract $fileRepo */
        $fileRepo = $this->app->make(FileAssetRepositoryContract::class);
        $fileRepo->save(FileAssetRecord::create(
            id: $fileAssetId,
            ownerType: 'static_page_attachment',
            ownerId: $mediaAssetId,
            originalFilename: $seed.'.jpg',
            storageDisk: 'public',
            storagePath: 'cms-media/'.$seed.'.jpg',
            mimeType: 'image/jpeg',
            fileSizeBytes: 1024,
            fileHashSha256: bin2hex(random_bytes(32)), // 64-char hex SHA-256
        ));

        /** @var CmsMediaAssetRepositoryContract $mediaRepo */
        $mediaRepo = $this->app->make(CmsMediaAssetRepositoryContract::class);
        $mediaRepo->save(CmsMediaAssetRecord::create(
            id: $mediaAssetId,
            fileAssetId: $fileAssetId,
            mediaType: PublicMediaType::from($mediaType),
            state: PublicMediaState::PUBLISHED,
            altText: 'Test media',
            createdBy: 'test',
        ));

        return $mediaAssetId;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(?string $imageId, ?string $photoId): array
    {
        $trustees = [];
        if ($photoId !== null) {
            $trustees[] = [
                'name' => 'Dr. Anjali Rao',
                'role' => 'Chair',
                'photo_file_id' => $photoId,
                'bio' => 'A Sanskrit scholar and practising devotee.',
            ];
        }

        return [
            'version' => 1,
            'values' => [
                'eyebrow' => 'Our Values',
                'title' => 'Seva, Satya, and Smriti',
                'body' => 'The trust is sustained by three commitments.',
                'image_file_id' => $imageId,
                'alt_text' => 'Trust values',
            ],
            'timeline' => [
                [
                    'year' => 1998,
                    'title' => 'Temple founded',
                    'description' => 'A small group of devotees established the temple.',
                ],
            ],
            'trustees' => $trustees,
            'donate_cta' => [
                'eyebrow' => 'Offer Your Seva',
                'title' => "Help sustain the temple's daily work",
                'body' => 'Every offering supports daily pooja and Annadanam.',
                'cta_label' => 'Donate Now',
                'cta_url' => '/donate',
            ],
        ];
    }
}
