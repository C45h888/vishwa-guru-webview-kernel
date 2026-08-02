<?php

declare(strict_types=1);

namespace App\Cms\Domain\Repositories;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use App\Cms\Domain\ValueObjects\CmsMediaAssetRecord;

/**
 * Persistence boundary for cms_media_assets.
 *
 * Uses plain string $id (not EntityId) — cms_media_assets.id is the raw
 * 26-char ULID identifier, consistent with how the schema stores it as
 * TEXT without an entity-type prefix. This mirrors the file_assets
 * pattern: both are leaf-overlay tables whose primary keys are bare ULIDs.
 *
 * This contract covers INSERT + read. Update, softDelete, and state
 * transitions (publish/archive) are owned by the Phase 4 admin kernel
 * and are out of scope here.
 */
interface CmsMediaAssetRepositoryContract
{
    /**
     * Persist a new cms_media_assets row.
     *
     * @throws RuntimeException on adapter failure
     */
    public function save(CmsMediaAssetRecord $asset): void;

    /**
     * Find a cms_media_asset by its raw ULID string.
     *
     * @return CmsMediaAssetRecord|null
     */
    public function findById(string $id): ?CmsMediaAssetRecord;

    /**
     * Find a cms_media_asset by its file_asset_id (UNIQUE FK column).
     *
     * @return CmsMediaAssetRecord|null
     */
    public function findByFileAssetId(string $fileAssetId): ?CmsMediaAssetRecord;

    /**
     * Find all cms_media_assets of a given media type.
     *
     * @return array<int, CmsMediaAssetRecord>
     */
    public function findByType(PublicMediaType $type): array;

    /**
     * Find all cms_media_assets in a given state.
     *
     * @return array<int, CmsMediaAssetRecord>
     */
    public function findByState(PublicMediaState $state): array;
}
