<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Repositories;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\Repositories\CmsMediaAssetRepositoryContract;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;
use App\Persistence\Contracts\PersistenceAdapterContract;
use RuntimeException;

/**
 * Eloquent implementation of CmsMediaAssetRepositoryContract.
 *
 * Doctrine:
 *   - Uses PersistenceAdapterContract (NOT DB:: facade, NOT Eloquent) —
 *     same pattern as EloquentHeroBannerRepository / FileAssetRepository.
 *   - Soft-delete aware: queries add AND deleted_at IS NULL.
 *   - Idempotent save: throws RuntimeException on adapter failure.
 *
 * Note: cms_media_assets uses plain string $id (not EntityId). The contract
 * documents this; the schema stores raw 26-char ULIDs as TEXT without an
 * entity-type prefix.
 *
 * @implements CmsMediaAssetRepositoryContract
 */
final class EloquentCmsMediaAssetRepository implements CmsMediaAssetRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    public function save(CmsMediaAssetRecord $asset): void
    {
        $row = $asset->toArray();
        $sql = 'INSERT INTO cms_media_assets (
            id, file_asset_id, media_type, state,
            alt_text, caption, credit,
            width, height, focal_x, focal_y, variant_group_id,
            published_at, archived_at,
            created_at, updated_at, deleted_at,
            created_by, updated_by
        ) VALUES (
            :id, :file_asset_id, :media_type, :state,
            :alt_text, :caption, :credit,
            :width, :height, :focal_x, :focal_y, :variant_group_id,
            :published_at, :archived_at,
            :created_at, :updated_at, :deleted_at,
            :created_by, :updated_by
        )';

        $exec = $this->adapter->execute($sql, $this->rowToParams($row));
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentCmsMediaAssetRepository::save failed: '.($exec->error() ?? 'unknown'),
            );
        }
    }

    public function findById(string $id): ?CmsMediaAssetRecord
    {
        $result = $this->adapter->query(
            'SELECT * FROM cms_media_assets WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return CmsMediaAssetRecord::fromRow($result->value()[0]);
    }

    public function findByFileAssetId(string $fileAssetId): ?CmsMediaAssetRecord
    {
        $result = $this->adapter->query(
            'SELECT * FROM cms_media_assets WHERE file_asset_id = :fid AND deleted_at IS NULL LIMIT 1',
            ['fid' => $fileAssetId],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return CmsMediaAssetRecord::fromRow($result->value()[0]);
    }

    /**
     * @return array<int, CmsMediaAssetRecord>
     */
    public function findByType(PublicMediaType $type): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM cms_media_assets
             WHERE media_type = :type AND deleted_at IS NULL
             ORDER BY created_at DESC',
            ['type' => $type->value],
        );
        if ($result->isFailure()) {
            return [];
        }

        return $this->mapRows($result->value() ?? []);
    }

    /**
     * @return array<int, CmsMediaAssetRecord>
     */
    public function findByState(PublicMediaState $state): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM cms_media_assets
             WHERE state = :state AND deleted_at IS NULL
             ORDER BY created_at DESC',
            ['state' => $state->value],
        );
        if ($result->isFailure()) {
            return [];
        }

        return $this->mapRows($result->value() ?? []);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowToParams(array $row): array
    {
        return [
            'id' => $row['id'],
            'file_asset_id' => $row['file_asset_id'],
            'media_type' => $row['media_type'],
            'state' => $row['state'],
            'alt_text' => $row['alt_text'],
            'caption' => $row['caption'],
            'credit' => $row['credit'],
            'width' => $row['width'],
            'height' => $row['height'],
            'focal_x' => $row['focal_x'],
            'focal_y' => $row['focal_y'],
            'variant_group_id' => $row['variant_group_id'],
            'published_at' => $row['published_at'],
            'archived_at' => $row['archived_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
            'created_by' => $row['created_by'],
            'updated_by' => $row['updated_by'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<CmsMediaAssetRecord>
     */
    private function mapRows(array $rows): array
    {
        return array_map(
            static fn (array $row): CmsMediaAssetRecord => CmsMediaAssetRecord::fromRow($row),
            $rows,
        );
    }
}
