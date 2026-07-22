<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Repositories;

use App\Cms\Contracts\PublicMediaQueryContract;
use App\Cms\Domain\DTOs\PublicMediaProjection;
use App\Persistence\Contracts\PersistenceAdapterContract;

final class PublicMediaQuery implements PublicMediaQueryContract
{
    public function __construct(private readonly PersistenceAdapterContract $adapter) {}

    public function find(string $id): ?PublicMediaProjection
    {
        return $this->findMany([$id])[$id] ?? null;
    }

    /** @param list<string> $ids */
    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn ($id): bool => is_string($id) && $id !== '',
        )));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'asset_id_'.$index;
            $placeholders[] = ':'.$key;
            $params[$key] = $id;
        }

        $result = $this->adapter->query(
            'SELECT media.id, files.storage_disk, files.storage_path, files.mime_type,
                    files.file_size_bytes, files.file_hash_sha256,
                    media.state, media.alt_text, media.width, media.height,
                    media.caption, media.credit, media.archived_at
             FROM cms_media_assets AS media
             INNER JOIN file_assets AS files ON files.id = media.file_asset_id
             WHERE media.id IN ('.implode(', ', $placeholders).')
               AND media.deleted_at IS NULL
               AND files.deleted_at IS NULL',
            $params,
        );

        if ($result->isFailure()) {
            return [];
        }

        $projections = [];
        foreach ($result->value() as $row) {
            $projection = $this->fromRow($row);
            $projections[$projection->id] = $projection;
        }

        return $projections;
    }

    /** @param array<string, mixed> $row */
    private function fromRow(array $row): PublicMediaProjection
    {
        return new PublicMediaProjection(
            id: (string) $row['id'],
            storageDisk: (string) $row['storage_disk'],
            storagePath: (string) $row['storage_path'],
            mimeType: (string) $row['mime_type'],
            fileSizeBytes: (int) $row['file_size_bytes'],
            contentHash: (string) $row['file_hash_sha256'],
            isPublic: (string) ($row['state'] ?? '') === 'published',
            isArchived: ($row['archived_at'] ?? null) !== null || (string) ($row['state'] ?? '') === 'archived',
            altText: isset($row['alt_text']) ? (string) $row['alt_text'] : null,
            width: isset($row['width']) ? (int) $row['width'] : null,
            height: isset($row['height']) ? (int) $row['height'] : null,
            caption: isset($row['caption']) ? (string) $row['caption'] : null,
            credit: isset($row['credit']) ? (string) $row['credit'] : null,
        );
    }
}
