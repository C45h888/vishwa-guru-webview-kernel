<?php

declare(strict_types=1);

namespace App\Gallery\Infrastructure\Repositories;

use App\Gallery\Domain\DTOs\GalleryDetailDTO;
use App\Gallery\Domain\DTOs\GalleryImageDTO;
use App\Gallery\Domain\DTOs\GallerySummaryDTO;
use App\Gallery\Domain\Repositories\GalleryImageRepositoryContract;
use App\Gallery\Domain\Repositories\GalleryRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use DateTimeImmutable;
use RuntimeException;

/**
 * Eloquent-style (raw SQL) implementation of GalleryRepositoryContract.
 *
 * Doctrine: never touches DB:: facade; reads via
 * PersistenceAdapterContract::query(). Soft-delete and published-state
 * filters live in SQL so the existing partial indexes are used.
 *
 * Displayable = state = 'published' AND deleted_at IS NULL.
 *
 * `image_count` is computed in the same query via a LEFT JOIN against
 * gallery_images, avoiding an N+1 follow-up query per gallery.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/database/schema-neon/V1-schema.sql lines 762-789
 *
 * @implements GalleryRepositoryContract
 */
final class EloquentGalleryRepository implements GalleryRepositoryContract
{
    private const COLUMNS = <<<'COLS'
        id, slug, title, description, cover_image_file_id,
        state, display_order, is_featured, published_at, metadata,
        created_at, updated_at
    COLS;

    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function findById(string $id): ?GalleryDetailDTO
    {
        $row = $this->fetchOne(
            'SELECT '.self::COLUMNS.'
             FROM   galleries
             WHERE  id = :id
               AND  deleted_at IS NULL
               AND  state = \'published\'
             LIMIT  1',
            ['id' => $id],
        );
        if ($row === null) {
            return null;
        }

        return $this->detailFromRow($row);
    }

    public function findBySlug(string $slug): ?GalleryDetailDTO
    {
        $row = $this->fetchOne(
            'SELECT '.self::COLUMNS.'
             FROM   galleries
             WHERE  slug = :slug
               AND  deleted_at IS NULL
               AND  state = \'published\'
             LIMIT  1',
            ['slug' => $slug],
        );
        if ($row === null) {
            return null;
        }

        return $this->detailFromRow($row);
    }

    public function listDisplayable(int $perPage, int $offset): array
    {
        $gColumns = self::prefixedColumns('g');
        $sql = 'SELECT '.$gColumns.',
                       COUNT(gi.id) AS image_count,
                       COUNT(*) OVER () AS total_count
                FROM   galleries g
                LEFT JOIN gallery_images gi
                       ON gi.gallery_id = g.id
                      AND gi.deleted_at IS NULL
                      AND gi.state = \'published\'
                WHERE  g.deleted_at IS NULL
                  AND  g.state = \'published\'
                GROUP BY g.id
                ORDER BY g.is_featured DESC,
                         g.display_order ASC,
                         g.published_at DESC NULLS LAST,
                         g.id ASC
                LIMIT  :per_page OFFSET :offset';

        $result = $this->adapter->query($sql, [
            'per_page' => $perPage,
            'offset'   => $offset,
        ]);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentGalleryRepository::listDisplayable failed: '.($result->error() ?? 'unknown')
            );
        }

        $rows = $result->value();
        $total = isset($rows[0]) ? (int) ($rows[0]['total_count'] ?? 0) : 0;
        $items = array_map(
            fn (array $row): GallerySummaryDTO => $this->summaryFromRow($row),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    public function listFeatured(int $limit): array
    {
        $gColumns = self::prefixedColumns('g');
        $sql = 'SELECT '.$gColumns.',
                       COUNT(gi.id) AS image_count
                FROM   galleries g
                LEFT JOIN gallery_images gi
                       ON gi.gallery_id = g.id
                      AND gi.deleted_at IS NULL
                      AND gi.state = \'published\'
                WHERE  g.deleted_at IS NULL
                  AND  g.state = \'published\'
                  AND  g.is_featured = 1
                GROUP BY g.id
                ORDER BY g.display_order ASC,
                         g.published_at DESC NULLS LAST,
                         g.id ASC
                LIMIT  :limit';

        $result = $this->adapter->query($sql, ['limit' => $limit]);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentGalleryRepository::listFeatured failed: '.($result->error() ?? 'unknown')
            );
        }

        return array_map(
            fn (array $row): GallerySummaryDTO => $this->summaryFromRow($row),
            $result->value(),
        );
    }

    /**
     * Prefix every column name with the given alias.
     */
    private static function prefixedColumns(string $alias): string
    {
        $cols = array_map('trim', explode(',', self::COLUMNS));

        return implode(', ', array_map(
            static fn (string $c): string => $alias.'.'.$c,
            $cols,
        ));
    }

    /**
     * @param  array<string, scalar|null>  $params
     * @return array<string, mixed>|null
     */
    private function fetchOne(string $sql, array $params): ?array
    {
        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentGalleryRepository fetch failed: '.($result->error() ?? 'unknown')
            );
        }
        $rows = $result->value();

        return $rows === [] ? null : $rows[0];
    }

    /**
     * @param  array<string, mixed>  $row  MUST include 'image_count'
     */
    private function summaryFromRow(array $row): GallerySummaryDTO
    {
        $short = $row['description'] ?? null;
        $shortTruncated = is_string($short) ? self::truncate($short, 200) : null;

        return new GallerySummaryDTO(
            id: (string) $row['id'],
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            shortDescription: $shortTruncated,
            coverImageFileId: self::nullIfEmpty($row['cover_image_file_id'] ?? null),
            imageCount: (int) ($row['image_count'] ?? 0),
            isFeatured: self::bool($row['is_featured'] ?? false),
            publishedAt: self::parseDate($row['published_at'] ?? null),
            state: (string) $row['state'],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function detailFromRow(array $row): GalleryDetailDTO
    {
        $short = $row['description'] ?? null;
        $shortTruncated = is_string($short) ? self::truncate($short, 200) : null;

        $images = $this->loadImages((string) $row['id']);

        return new GalleryDetailDTO(
            id: (string) $row['id'],
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            shortDescription: $shortTruncated,
            description: self::nullIfEmpty($row['description'] ?? null),
            coverImageFileId: self::nullIfEmpty($row['cover_image_file_id'] ?? null),
            imageCount: count($images),
            isFeatured: self::bool($row['is_featured'] ?? false),
            displayOrder: (int) ($row['display_order'] ?? 0),
            publishedAt: self::parseDate($row['published_at'] ?? null),
            state: (string) $row['state'],
            metadata: self::decodeJson($row['metadata'] ?? null),
            createdAt: self::parseDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            images: $images,
        );
    }

    /**
     * Loads the gallery's published images via an inline query.
     * Kept here (rather than in the image repo) because the gallery
     * detail surface is a single round-trip concept — callers want
     * `findById` to return the fully-assembled DTO with images included.
     *
     * @return list<GalleryImageDTO>
     */
    private function loadImages(string $galleryId): array
    {
        $sql = 'SELECT id, gallery_id, file_asset_id, title, caption, alt_text,
                       photographer_credit, taken_at, display_order, is_featured,
                       state, published_at
                FROM   gallery_images
                WHERE  gallery_id = :gid
                  AND  deleted_at IS NULL
                  AND  state = \'published\'
                ORDER BY display_order ASC, id ASC';

        $result = $this->adapter->query($sql, ['gid' => $galleryId]);
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            fn (array $row): GalleryImageDTO => self::imageFromRow($row),
            $result->value(),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function imageFromRow(array $row): GalleryImageDTO
    {
        return new GalleryImageDTO(
            id: (string) $row['id'],
            galleryId: (string) $row['gallery_id'],
            fileAssetId: self::nullIfEmpty($row['file_asset_id'] ?? null),
            title: self::nullIfEmpty($row['title'] ?? null),
            caption: self::nullIfEmpty($row['caption'] ?? null),
            altText: self::nullIfEmpty($row['alt_text'] ?? null),
            photographerCredit: self::nullIfEmpty($row['photographer_credit'] ?? null),
            takenAt: self::parseDate($row['taken_at'] ?? null),
            displayOrder: (int) ($row['display_order'] ?? 0),
            isFeatured: self::bool($row['is_featured'] ?? false),
            publishedAt: self::parseDate($row['published_at'] ?? null),
        );
    }

    private static function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private static function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = (string) $value;

        return $str === '' ? null : $str;
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 't', 'yes'], true);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            /** @var array<string, mixed> $value */
            return $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
