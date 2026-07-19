<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use App\Persistence\Contracts\PersistenceAdapterContract;
use RuntimeException;

/**
 * FileAssetRepository — concrete impl of FileAssetRepositoryContract.
 *
 * Doctrine:
 *   - Uses PersistenceAdapterContract (NOT DB:: facade) — same pattern
 *     as DonorRepository / PaymentRepository.
 *   - Soft-delete aware: queries exclude rows where deleted_at IS NOT NULL.
 *   - Idempotent save: throws RuntimeException on adapter failure; never
 *     silently loses data.
 *
 * Note: file_assets uses plain string $id (not EntityId). The contract
 * documents this; the schema stores ULIDs as TEXT without an entity-type
 * prefix.
 */
final class FileAssetRepository implements FileAssetRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    public function save(FileAssetRecord $file): void
    {
        $row = $file->toArray();

        $sql = 'INSERT INTO file_assets (
                    id, owner_type, owner_id, original_filename,
                    storage_disk, storage_path, mime_type, file_size_bytes,
                    file_hash_sha256, purpose, is_public, is_archived,
                    archived_at, metadata, uploaded_at, created_at, updated_at
                ) VALUES (
                    :id, :owner_type, :owner_id, :original_filename,
                    :storage_disk, :storage_path, :mime_type, :file_size_bytes,
                    :file_hash_sha256, :purpose, :is_public, :is_archived,
                    :archived_at, :metadata, :uploaded_at, :created_at, :updated_at
                )';

        $params = [
            'id'                => $row['id'],
            'owner_type'        => $row['owner_type'],
            'owner_id'          => $row['owner_id'],
            'original_filename' => $row['original_filename'],
            'storage_disk'      => $row['storage_disk'],
            'storage_path'      => $row['storage_path'],
            'mime_type'         => $row['mime_type'],
            'file_size_bytes'   => $row['file_size_bytes'],
            'file_hash_sha256'  => $row['file_hash_sha256'],
            'purpose'           => $row['purpose'] ?? null,
            'is_public'         => (int) ($row['is_public'] ?? 0),
            'is_archived'       => (int) ($row['is_archived'] ?? 0),
            'archived_at'       => $row['archived_at'] ?? null,
            'metadata'          => is_array($row['metadata'] ?? null)
                ? json_encode($row['metadata'])
                : ($row['metadata'] ?? '{}'),
            'uploaded_at'       => $row['uploaded_at'],
            'created_at'        => $row['created_at'],
            'updated_at'        => $row['updated_at'],
        ];

        $result = $this->adapter->execute($sql, $params);

        if ($result->isFailure()) {
            throw new RuntimeException(
                'FileAssetRepository save failed: '.$result->error(),
            );
        }
    }

    public function findById(string $id): ?FileAssetRecord
    {
        $result = $this->adapter->query(
            'SELECT * FROM file_assets WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id],
        );

        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return FileAssetRecord::fromRow($result->value()[0]);
    }

    public function findByHash(string $sha256): ?FileAssetRecord
    {
        $result = $this->adapter->query(
            'SELECT * FROM file_assets WHERE file_hash_sha256 = :hash AND deleted_at IS NULL LIMIT 1',
            ['hash' => $sha256],
        );

        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return FileAssetRecord::fromRow($result->value()[0]);
    }

    public function findByOwner(string $ownerType, string $ownerId): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM file_assets
             WHERE owner_type = :owner_type AND owner_id = :owner_id
               AND deleted_at IS NULL
             ORDER BY uploaded_at DESC',
            [
                'owner_type' => $ownerType,
                'owner_id'   => $ownerId,
            ],
        );

        if ($result->isFailure()) {
            return [];
        }

        $rows = $result->value() ?? [];
        return array_map(
            static fn (array $row): FileAssetRecord => FileAssetRecord::fromRow($row),
            $rows,
        );
    }

    public function findByPurpose(string $purpose): array
    {
        $result = $this->adapter->query(
            'SELECT * FROM file_assets
             WHERE purpose = :purpose
               AND deleted_at IS NULL
             ORDER BY uploaded_at DESC',
            ['purpose' => $purpose],
        );

        if ($result->isFailure()) {
            return [];
        }

        $rows = $result->value() ?? [];
        return array_map(
            static fn (array $row): FileAssetRecord => FileAssetRecord::fromRow($row),
            $rows,
        );
    }
}