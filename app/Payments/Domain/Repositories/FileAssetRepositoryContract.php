<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\ValueObjects\FileAssetRecord;

/**
 * Persistence boundary for file_assets.
 *
 * Uses plain string $id (not EntityId) — file_assets.id is the raw
 * ULID identifier, consistent with how the schema references it as
 * a TEXT primary key without the entity-type prefix that EntityId uses.
 */
interface FileAssetRepositoryContract
{
    /**
     * Persist a new file_asset row.
     */
    public function save(FileAssetRecord $file): void;

    /**
     * Find a file asset by its ULID string.
     *
     * @return FileAssetRecord|null
     */
    public function findById(string $id): ?FileAssetRecord;

    /**
     * Find a file asset by its SHA-256 content hash.
     * Used to detect duplicate uploads.
     *
     * @return FileAssetRecord|null
     */
    public function findByHash(string $sha256): ?FileAssetRecord;

    /**
     * Find all file assets owned by a given entity.
     *
     * @param  string  $ownerType  e.g. 'receipt', 'certificate_80g'
     * @param  string  $ownerId    ULID of the owning entity
     * @return array<int, FileAssetRecord>
     */
    public function findByOwner(string $ownerType, string $ownerId): array;

    /**
     * Find all file assets with a given purpose tag.
     *
     * @param  string  $purpose  purpose value from the file_assets.purpose column
     * @return array<int, FileAssetRecord>
     */
    public function findByPurpose(string $purpose): array;
}
