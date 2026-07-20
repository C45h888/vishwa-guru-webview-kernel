<?php

declare(strict_types=1);

namespace App\Gallery\Infrastructure\Repositories;

use App\Gallery\Domain\DTOs\GalleryImageDTO;
use App\Gallery\Domain\Repositories\GalleryImageRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use RuntimeException;

/**
 * Eloquent-style (raw SQL) implementation of GalleryImageRepositoryContract.
 *
 * Doctrine: never touches DB:: facade; reads via
 * PersistenceAdapterContract::query(). State filter = 'published' AND
 * deleted_at IS NULL. Ordered by display_order ASC, id ASC.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/database/schema-neon/V1-schema.sql lines 791-823
 *
 * @implements GalleryImageRepositoryContract
 */
final class EloquentGalleryImageRepository implements GalleryImageRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function listByGallery(string $galleryId, ?int $limit): array
    {
        $sql = 'SELECT id, gallery_id, file_asset_id, title, caption, alt_text,
                       photographer_credit, taken_at, display_order, is_featured,
                       state, published_at
                FROM   gallery_images
                WHERE  gallery_id = :gid
                  AND  deleted_at IS NULL
                  AND  state = \'published\'
                ORDER BY display_order ASC, id ASC';

        $params = ['gid' => $galleryId];
        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
            $params['limit'] = $limit;
        }

        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentGalleryImageRepository::listByGallery failed: '.($result->error() ?? 'unknown')
            );
        }

        return array_map(
            static fn (array $row): GalleryImageDTO => EloquentGalleryRepository::imageFromRow($row),
            $result->value(),
        );
    }
}
