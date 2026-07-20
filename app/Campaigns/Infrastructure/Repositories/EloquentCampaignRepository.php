<?php

declare(strict_types=1);

namespace App\Campaigns\Infrastructure\Repositories;

use App\Campaigns\Domain\DTOs\CampaignDetailDTO;
use App\Campaigns\Domain\DTOs\CampaignSummaryDTO;
use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use DateTimeImmutable;
use RuntimeException;

/**
 * Eloquent-style (raw SQL) implementation of CampaignRepositoryContract.
 *
 * Doctrine: never touches DB:: facade; reads via
 * PersistenceAdapterContract::query(). Soft-delete and displayable-state
 * filters live in SQL (not in PHP) so the existing partial indexes are
 * used on the Postgres path.
 *
 * Displayable = state IN ('active','completed') AND deleted_at IS NULL.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/database/schema-neon/V1-schema.sql lines 381-424
 *
 * @implements CampaignRepositoryContract
 */
final class EloquentCampaignRepository implements CampaignRepositoryContract
{
    /** All read-side columns on the campaigns table (shared by every method). */
    private const COLUMNS = <<<'COLS'
        id, slug, title, description, short_description, category,
        target_amount_minor, currency_code, state, starts_at, ends_at,
        display_order, is_featured, cover_image_file_id, metadata,
        created_at, updated_at
    COLS;

    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function findById(string $id): ?CampaignDetailDTO
    {
        $row = $this->fetchOne(
            'SELECT '.self::COLUMNS.'
             FROM   campaigns
             WHERE  id = :id
               AND  deleted_at IS NULL
               AND  state IN (\'active\',\'completed\')
             LIMIT  1',
            ['id' => $id],
        );

        return $row === null ? null : $this->detailFromRow($row);
    }

    public function findBySlug(string $slug): ?CampaignDetailDTO
    {
        $row = $this->fetchOne(
            'SELECT '.self::COLUMNS.'
             FROM   campaigns
             WHERE  slug = :slug
               AND  deleted_at IS NULL
               AND  state IN (\'active\',\'completed\')
             LIMIT  1',
            ['slug' => $slug],
        );

        return $row === null ? null : $this->detailFromRow($row);
    }

    public function listDisplayable(int $perPage, int $offset): array
    {
        $sql = 'SELECT '.self::COLUMNS.',
                       COUNT(*) OVER () AS total_count
                FROM   campaigns
                WHERE  deleted_at IS NULL
                  AND  state IN (\'active\',\'completed\')
                ORDER BY is_featured DESC,
                         display_order ASC,
                         starts_at DESC NULLS LAST,
                         id ASC
                LIMIT  :per_page OFFSET :offset';

        $result = $this->adapter->query($sql, [
            'per_page' => $perPage,
            'offset'   => $offset,
        ]);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentCampaignRepository::listDisplayable failed: '.($result->error() ?? 'unknown')
            );
        }

        $rows = $result->value();
        $total = isset($rows[0]) ? (int) ($rows[0]['total_count'] ?? 0) : 0;
        $items = array_map(
            fn (array $row): CampaignSummaryDTO => $this->summaryFromRow($row),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    public function listFeatured(int $limit): array
    {
        $sql = 'SELECT '.self::COLUMNS.'
                FROM   campaigns
                WHERE  deleted_at IS NULL
                  AND  state IN (\'active\',\'completed\')
                  AND  is_featured = 1
                ORDER BY display_order ASC,
                         starts_at DESC NULLS LAST,
                         id ASC
                LIMIT  :limit';

        $result = $this->adapter->query($sql, ['limit' => $limit]);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentCampaignRepository::listFeatured failed: '.($result->error() ?? 'unknown')
            );
        }

        return array_map(
            fn (array $row): CampaignSummaryDTO => $this->summaryFromRow($row),
            $result->value(),
        );
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
                'EloquentCampaignRepository fetch failed: '.($result->error() ?? 'unknown')
            );
        }
        $rows = $result->value();

        return $rows === [] ? null : $rows[0];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function summaryFromRow(array $row): CampaignSummaryDTO
    {
        return new CampaignSummaryDTO(
            id: (string) $row['id'],
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            shortDescription: self::nullIfEmpty($row['short_description'] ?? null),
            category: (string) $row['category'],
            currencyCode: (string) $row['currency_code'],
            targetAmountMinor: $row['target_amount_minor'] !== null ? (int) $row['target_amount_minor'] : null,
            isFeatured: self::bool($row['is_featured'] ?? false),
            state: (string) $row['state'],
            startsAt: self::parseDate($row['starts_at'] ?? null),
            endsAt: self::parseDate($row['ends_at'] ?? null),
            coverImageFileId: self::nullIfEmpty($row['cover_image_file_id'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function detailFromRow(array $row): CampaignDetailDTO
    {
        $state = (string) $row['state'];

        return new CampaignDetailDTO(
            id: (string) $row['id'],
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            shortDescription: self::nullIfEmpty($row['short_description'] ?? null),
            description: self::nullIfEmpty($row['description'] ?? null),
            category: (string) $row['category'],
            currencyCode: (string) $row['currency_code'],
            targetAmountMinor: $row['target_amount_minor'] !== null ? (int) $row['target_amount_minor'] : null,
            isFeatured: self::bool($row['is_featured'] ?? false),
            isActive: $state === 'active',
            state: $state,
            displayOrder: (int) ($row['display_order'] ?? 0),
            startsAt: self::parseDate($row['starts_at'] ?? null),
            endsAt: self::parseDate($row['ends_at'] ?? null),
            coverImageFileId: self::nullIfEmpty($row['cover_image_file_id'] ?? null),
            metadata: self::decodeJson($row['metadata'] ?? null),
            createdAt: self::parseDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
        );
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
     * Decode JSON-as-TEXT (SQLite mirror) or pass-through array (PG JSONB).
     *
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
