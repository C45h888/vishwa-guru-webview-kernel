<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\Repositories\CmsMediaAssetRepositoryContract;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Integration test for CmsMediaAssetRepositoryContract.
 *
 * Uses the SQLite in-memory database (RefreshDatabase) so this test
 * is fully self-contained and does not require a live Postgres/Neon DB.
 */
final class CmsMediaAssetRepositoryTest extends InfrastructureTestCase
{
    private CmsMediaAssetRepositoryContract $repo;
    private FileAssetRepositoryContract $fileRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->app->make(CmsMediaAssetRepositoryContract::class);
        $this->fileRepo = $this->app->make(FileAssetRepositoryContract::class);
    }

    public function test_save_and_find_by_id(): void
    {
        $fileAssetId = 'file_asset_01KYSW47BTST000000000FA';
        $mediaId = 'cms_media_01KYSW47BTST000000000MA';

        $this->fileRepo->save(FileAssetRecord::create(
            id: $fileAssetId,
            ownerType: 'avatar',
            ownerId: $mediaId,
            originalFilename: 'test-image.jpg',
            storageDisk: 'public',
            storagePath: 'cms-media/test-image.jpg',
            mimeType: 'image/jpeg',
            fileSizeBytes: 102400,
            fileHashSha256: str_repeat('a', 64), // valid 64-char hex
        ));

        $this->repo->save(CmsMediaAssetRecord::create(
            id: $mediaId,
            fileAssetId: $fileAssetId,
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::PUBLISHED,
            altText: 'Test alt text',
            width: 1920,
            height: 1080,
            publishedAt: new \DateTimeImmutable('2026-07-01T00:00:00+00:00'),
            createdBy: 'test',
        ));

        $found = $this->repo->findById($mediaId);

        $this->assertNotNull($found);
        $this->assertSame($mediaId, $found->id());
        $this->assertSame(PublicMediaType::CONTENT_BLOCK_IMAGE, $found->mediaType());
        $this->assertSame(PublicMediaState::PUBLISHED, $found->state());
        $this->assertSame('Test alt text', $found->altText());
        $this->assertSame(1920, $found->width());
        $this->assertSame(1080, $found->height());
    }

    public function test_find_by_file_asset_id(): void
    {
        $fileAssetId = 'file_asset_01KYSW47BTST000000000FB';
        $mediaId = 'cms_media_01KYSW47BTST000000000MB';

        $this->fileRepo->save(FileAssetRecord::create(
            id: $fileAssetId,
            ownerType: 'avatar',
            ownerId: $mediaId,
            originalFilename: 'hero.png',
            storageDisk: 'public',
            storagePath: 'cms-media/hero.png',
            mimeType: 'image/png',
            fileSizeBytes: 204800,
            fileHashSha256: str_repeat('b', 64),
        ));

        $this->repo->save(CmsMediaAssetRecord::create(
            id: $mediaId,
            fileAssetId: $fileAssetId,
            mediaType: PublicMediaType::HERO_DESKTOP,
            state: PublicMediaState::PUBLISHED,
            altText: 'Hero banner',
            width: 2786,
            height: 2094,
            publishedAt: new \DateTimeImmutable('2026-07-01T00:00:00+00:00'),
            createdBy: 'test',
        ));

        $found = $this->repo->findByFileAssetId($fileAssetId);

        $this->assertNotNull($found);
        $this->assertSame($mediaId, $found->id());
        $this->assertSame(PublicMediaType::HERO_DESKTOP, $found->mediaType());
    }

    public function test_find_by_type_returns_only_matching_type(): void
    {
        $fileAssetId1 = 'file_asset_01KYSW47BTST000000000FC';
        $mediaId1 = 'cms_media_01KYSW47BTST000000MC01';
        $fileAssetId2 = 'file_asset_01KYSW47BTST000000000FD';
        $mediaId2 = 'cms_media_01KYSW47BTST000000MC02';

        $this->fileRepo->save(FileAssetRecord::create(
            id: $fileAssetId1,
            ownerType: 'avatar',
            ownerId: $mediaId1,
            originalFilename: 'img1.jpg',
            storageDisk: 'public',
            storagePath: 'cms-media/img1.jpg',
            mimeType: 'image/jpeg',
            fileSizeBytes: 51200,
            fileHashSha256: str_repeat('c', 64),
        ));
        $this->fileRepo->save(FileAssetRecord::create(
            id: $fileAssetId2,
            ownerType: 'avatar',
            ownerId: $mediaId2,
            originalFilename: 'img2.jpg',
            storageDisk: 'public',
            storagePath: 'cms-media/img2.jpg',
            mimeType: 'image/jpeg',
            fileSizeBytes: 51200,
            fileHashSha256: str_repeat('d', 64),
        ));

        $this->repo->save(CmsMediaAssetRecord::create(
            id: $mediaId1,
            fileAssetId: $fileAssetId1,
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::DRAFT,
            createdBy: 'test',
        ));
        $this->repo->save(CmsMediaAssetRecord::create(
            id: $mediaId2,
            fileAssetId: $fileAssetId2,
            mediaType: PublicMediaType::GALLERY_IMAGE,
            state: PublicMediaState::DRAFT,
            createdBy: 'test',
        ));

        $results = $this->repo->findByType(PublicMediaType::CONTENT_BLOCK_IMAGE);

        $this->assertCount(1, $results);
        $this->assertSame($mediaId1, $results[0]->id());
    }

    public function test_find_by_state_returns_only_matching_state(): void
    {
        $fileAssetId1 = 'file_asset_01KYSW47BTST000000000FE';
        $mediaId1 = 'cms_media_01KYSW47BTST000000MC03';
        $fileAssetId2 = 'file_asset_01KYSW47BTST000000000FF';
        $mediaId2 = 'cms_media_01KYSW47BTST000000MC04';

        $this->fileRepo->save(FileAssetRecord::create(
            id: $fileAssetId1,
            ownerType: 'avatar',
            ownerId: $mediaId1,
            originalFilename: 'pub.jpg',
            storageDisk: 'public',
            storagePath: 'cms-media/pub.jpg',
            mimeType: 'image/jpeg',
            fileSizeBytes: 51200,
            fileHashSha256: str_repeat('e', 64),
        ));
        $this->fileRepo->save(FileAssetRecord::create(
            id: $fileAssetId2,
            ownerType: 'avatar',
            ownerId: $mediaId2,
            originalFilename: 'draft.jpg',
            storageDisk: 'public',
            storagePath: 'cms-media/draft.jpg',
            mimeType: 'image/jpeg',
            fileSizeBytes: 51200,
            fileHashSha256: str_repeat('f', 64),
        ));

        $this->repo->save(CmsMediaAssetRecord::create(
            id: $mediaId1,
            fileAssetId: $fileAssetId1,
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::PUBLISHED,
            altText: 'Published',
            publishedAt: new \DateTimeImmutable('2026-07-01T00:00:00+00:00'),
            createdBy: 'test',
        ));
        $this->repo->save(CmsMediaAssetRecord::create(
            id: $mediaId2,
            fileAssetId: $fileAssetId2,
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::DRAFT,
            createdBy: 'test',
        ));

        $drafts = $this->repo->findByState(PublicMediaState::DRAFT);
        $published = $this->repo->findByState(PublicMediaState::PUBLISHED);

        $this->assertCount(1, $drafts);
        $this->assertSame($mediaId2, $drafts[0]->id());

        $this->assertCount(1, $published);
        $this->assertSame($mediaId1, $published[0]->id());
    }

    public function test_find_by_id_returns_null_for_nonexistent(): void
    {
        $found = $this->repo->findById('nonexistent_id_01KYSW47BTST0000000');
        $this->assertNull($found);
    }

    public function test_find_by_file_asset_id_returns_null_for_nonexistent(): void
    {
        $found = $this->repo->findByFileAssetId('nonexistent_file_01KYSW47BTST');
        $this->assertNull($found);
    }
}
