<?php

declare(strict_types=1);

namespace Tests\Unit\Cms\Domain\ValueObjects;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CmsMediaAssetRecord validation logic.
 *
 * Covers CHECK constraint enforcement that the Postgres DB would apply
 * but SQLite (used in test) cannot enforce — the PHP layer must validate.
 */
final class CmsMediaAssetRecordTest extends TestCase
{
    // ─── Happy path ───────────────────────────────────────────────────

    public function test_create_published_asset_with_all_required_fields(): void
    {
        $record = CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000000',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000000',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::PUBLISHED,
            altText: 'A test image',
            width: 1920,
            height: 1080,
            publishedAt: new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
            createdBy: 'test',
        );

        $this->assertSame('cms_media_01KYSW47BTEST00000000000', $record->id());
        $this->assertSame(PublicMediaType::CONTENT_BLOCK_IMAGE, $record->mediaType());
        $this->assertSame(PublicMediaState::PUBLISHED, $record->state());
        $this->assertSame('A test image', $record->altText());
        $this->assertSame(1920, $record->width());
        $this->assertSame(1080, $record->height());
        $this->assertNotNull($record->publishedAt());
        $this->assertSame('test', $record->createdBy());
    }

    public function test_create_draft_asset_requires_no_publish_fields(): void
    {
        $record = CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000001',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000001',
            mediaType: PublicMediaType::HERO_DESKTOP,
            state: PublicMediaState::DRAFT,
        );

        $this->assertSame(PublicMediaState::DRAFT, $record->state());
        $this->assertNull($record->altText());
        $this->assertNull($record->publishedAt());
    }

    // ─── cms_media_publish_metadata CHECK — PUBLISHED requires altText ─────

    public function test_create_published_without_alt_text_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('published assets must have altText');

        CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000002',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000002',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::PUBLISHED,
            altText: null,
            publishedAt: new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
        );
    }

    public function test_create_published_with_empty_alt_text_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('published assets must have altText');

        CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000003',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000003',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::PUBLISHED,
            altText: '',
            publishedAt: new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
        );
    }

    // ─── cms_media_publish_metadata CHECK — PUBLISHED requires publishedAt ─

    public function test_create_published_without_published_at_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('published assets must have publishedAt');

        CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000004',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000004',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::PUBLISHED,
            altText: 'Some alt text',
            publishedAt: null,
        );
    }

    // ─── cms_media_archive_timestamp CHECK — ARCHIVED requires archivedAt ─

    public function test_create_archived_without_archived_at_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('archived assets must have archivedAt');

        CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000005',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000005',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::ARCHIVED,
            archivedAt: null,
        );
    }

    // ─── width / height must be null or > 0 ───────────────────────────────

    public function test_create_with_zero_width_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('width must be null or > 0');

        CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000006',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000006',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::DRAFT,
            width: 0,
        );
    }

    public function test_create_with_negative_width_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('width must be null or > 0');

        CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000007',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000007',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::DRAFT,
            width: -100,
        );
    }

    public function test_create_with_zero_height_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('height must be null or > 0');

        CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000008',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000008',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::DRAFT,
            height: 0,
        );
    }

    // ─── id / fileAssetId non-empty ───────────────────────────────────────

    public function test_create_with_empty_id_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('id cannot be empty');

        CmsMediaAssetRecord::create(
            id: '',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000009',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::DRAFT,
        );
    }

    public function test_create_with_empty_file_asset_id_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fileAssetId cannot be empty');

        CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000010',
            fileAssetId: '',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::DRAFT,
        );
    }

    // ─── fromRow ──────────────────────────────────────────────────────────

    public function test_from_row_hydrates_all_fields(): void
    {
        $row = [
            'id' => 'cms_media_01KYSW47BTST00000000001',
            'file_asset_id' => 'file_asset_01KYSW47BTST000000001',
            'media_type' => 'hero_desktop',
            'state' => 'published',
            'alt_text' => 'Hero image',
            'caption' => 'A caption',
            'credit' => 'Photographer',
            'width' => 2786,
            'height' => 2094,
            'focal_x' => null,
            'focal_y' => null,
            'variant_group_id' => null,
            'published_at' => '2026-07-01T00:00:00+00:00',
            'archived_at' => null,
            'created_at' => '2026-07-01T00:00:00+00:00',
            'updated_at' => '2026-07-01T00:00:00+00:00',
            'deleted_at' => null,
            'created_by' => 'system',
            'updated_by' => 'system',
        ];

        $record = CmsMediaAssetRecord::fromRow($row);

        $this->assertSame('cms_media_01KYSW47BTST00000000001', $record->id());
        $this->assertSame(PublicMediaType::HERO_DESKTOP, $record->mediaType());
        $this->assertSame(PublicMediaState::PUBLISHED, $record->state());
        $this->assertSame('Hero image', $record->altText());
        $this->assertSame(2786, $record->width());
        $this->assertSame(2094, $record->height());
        $this->assertSame('Photographer', $record->credit());
        $this->assertNotNull($record->publishedAt());
    }

    public function test_from_row_throws_on_missing_required_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('row missing required key: media_type');

        CmsMediaAssetRecord::fromRow([
            'id' => 'cms_media_01KYSW47BTST00000000002',
            'file_asset_id' => 'file_asset_01KYSW47BTST000000002',
            // 'media_type' missing
            'state' => 'draft',
            'created_at' => '2026-07-01T00:00:00+00:00',
            'updated_at' => '2026-07-01T00:00:00+00:00',
        ]);
    }

    // ─── toArray ─────────────────────────────────────────────────────────

    public function test_to_array_returns_snake_case_keys(): void
    {
        $record = CmsMediaAssetRecord::create(
            id: 'cms_media_01KYSW47BTEST00000000011',
            fileAssetId: 'file_asset_01KYSW47BTEST0000000011',
            mediaType: PublicMediaType::CONTENT_BLOCK_IMAGE,
            state: PublicMediaState::DRAFT,
            width: 800,
            height: 600,
        );

        $arr = $record->toArray();

        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('file_asset_id', $arr);
        $this->assertArrayHasKey('media_type', $arr);
        $this->assertArrayHasKey('state', $arr);
        $this->assertArrayHasKey('width', $arr);
        $this->assertArrayHasKey('height', $arr);
        $this->assertArrayHasKey('created_at', $arr);
        $this->assertArrayHasKey('updated_at', $arr);
        $this->assertSame('content_block_image', $arr['media_type']);
        $this->assertSame('draft', $arr['state']);
    }
}
