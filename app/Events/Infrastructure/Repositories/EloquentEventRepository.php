<?php

declare(strict_types=1);

namespace App\Events\Infrastructure\Repositories;

use App\Events\Domain\DTOs\EventDetailDTO;
use App\Events\Domain\DTOs\EventSummaryDTO;
use App\Events\Domain\Repositories\EventRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use DateTimeImmutable;
use RuntimeException;

/**
 * Eloquent-style (raw SQL) implementation of EventRepositoryContract.
 *
 * Doctrine: never touches DB:: facade; reads via
 * PersistenceAdapterContract::query(). Displayable states =
 * 'published' | 'completed'. The `starts_at >= :now` /
 * `starts_at < :now` predicates are passed as bind parameters because
 * PG rejects NOW() in partial-index predicates (see V1-schema.sql
 * comment around line 866-870).
 *
 * `isUpcoming` is computed at DTO build time as `$startsAt >= $now`.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/database/schema-neon/V1-schema.sql lines 829-875
 *
 * @implements EventRepositoryContract
 */
final class EloquentEventRepository implements EventRepositoryContract
{
    private const COLUMNS = <<<'COLS'
        id, slug, title, description, short_description, banner_file_id,
        starts_at, ends_at, timezone, venue, venue_address, state,
        published_at, completed_at, is_featured, display_order, metadata,
        created_at, updated_at
    COLS;

    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function findById(string $id): ?EventDetailDTO
    {
        $row = $this->fetchOne(
            'SELECT '.self::COLUMNS.'
             FROM   events
             WHERE  id = :id
               AND  deleted_at IS NULL
               AND  state IN (\'published\',\'completed\')
             LIMIT  1',
            ['id' => $id],
        );
        if ($row === null) {
            return null;
        }

        return $this->detailFromRow($row);
    }

    public function findBySlug(string $slug): ?EventDetailDTO
    {
        $row = $this->fetchOne(
            'SELECT '.self::COLUMNS.'
             FROM   events
             WHERE  slug = :slug
               AND  deleted_at IS NULL
               AND  state IN (\'published\',\'completed\')
             LIMIT  1',
            ['slug' => $slug],
        );
        if ($row === null) {
            return null;
        }

        return $this->detailFromRow($row);
    }

    public function listUpcoming(string $now, int $limit): array
    {
        $sql = 'SELECT '.self::COLUMNS.'
                FROM   events
                WHERE  deleted_at IS NULL
                  AND  state = \'published\'
                  AND  starts_at >= :now
                ORDER BY starts_at ASC, id ASC
                LIMIT  :limit';

        $result = $this->adapter->query($sql, [
            'now'   => $now,
            'limit' => $limit,
        ]);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentEventRepository::listUpcoming failed: '.($result->error() ?? 'unknown')
            );
        }

        $reference = self::parseDate($now) ?? new DateTimeImmutable();

        return array_map(
            fn (array $row): EventSummaryDTO => $this->summaryFromRow($row, $reference),
            $result->value(),
        );
    }

    public function listPast(string $now, int $perPage, int $offset): array
    {
        $sql = 'SELECT '.self::COLUMNS.',
                       COUNT(*) OVER () AS total_count
                FROM   events
                WHERE  deleted_at IS NULL
                  AND  state IN (\'published\',\'completed\')
                  AND  starts_at < :now
                ORDER BY starts_at DESC, id DESC
                LIMIT  :per_page OFFSET :offset';

        $result = $this->adapter->query($sql, [
            'now'      => $now,
            'per_page' => $perPage,
            'offset'   => $offset,
        ]);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentEventRepository::listPast failed: '.($result->error() ?? 'unknown')
            );
        }

        $rows = $result->value();
        $total = isset($rows[0]) ? (int) ($rows[0]['total_count'] ?? 0) : 0;
        $reference = self::parseDate($now) ?? new DateTimeImmutable();
        $items = array_map(
            fn (array $row): EventSummaryDTO => $this->summaryFromRow($row, $reference),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
    }

    public function listFeatured(int $limit): array
    {
        $sql = 'SELECT '.self::COLUMNS.'
                FROM   events
                WHERE  deleted_at IS NULL
                  AND  state IN (\'published\',\'completed\')
                  AND  is_featured = 1
                ORDER BY display_order ASC, starts_at ASC, id ASC
                LIMIT  :limit';

        $result = $this->adapter->query($sql, ['limit' => $limit]);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentEventRepository::listFeatured failed: '.($result->error() ?? 'unknown')
            );
        }

        $reference = new DateTimeImmutable();

        return array_map(
            fn (array $row): EventSummaryDTO => $this->summaryFromRow($row, $reference),
            $result->value(),
        );
    }

    public function listAll(int $perPage, int $offset): array
    {
        $sql = 'SELECT '.self::COLUMNS.',
                       COUNT(*) OVER () AS total_count
                FROM   events
                WHERE  deleted_at IS NULL
                  AND  state IN (\'published\',\'completed\')
                ORDER BY starts_at DESC, id DESC
                LIMIT  :per_page OFFSET :offset';

        $result = $this->adapter->query($sql, [
            'per_page' => $perPage,
            'offset'   => $offset,
        ]);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'EloquentEventRepository::listAll failed: '.($result->error() ?? 'unknown')
            );
        }

        $rows = $result->value();
        $total = isset($rows[0]) ? (int) ($rows[0]['total_count'] ?? 0) : 0;
        $reference = new DateTimeImmutable();
        $items = array_map(
            fn (array $row): EventSummaryDTO => $this->summaryFromRow($row, $reference),
            $rows,
        );

        return ['items' => $items, 'total' => $total];
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
                'EloquentEventRepository fetch failed: '.($result->error() ?? 'unknown')
            );
        }
        $rows = $result->value();

        return $rows === [] ? null : $rows[0];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function summaryFromRow(array $row, DateTimeImmutable $now): EventSummaryDTO
    {
        $startsAt = self::parseDate($row['starts_at'] ?? null) ?? new DateTimeImmutable();

        return new EventSummaryDTO(
            id: (string) $row['id'],
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            shortDescription: self::nullIfEmpty($row['short_description'] ?? null),
            startsAt: $startsAt,
            endsAt: self::parseDate($row['ends_at'] ?? null),
            timezone: (string) $row['timezone'],
            venue: self::nullIfEmpty($row['venue'] ?? null),
            venueAddress: self::nullIfEmpty($row['venue_address'] ?? null),
            state: (string) $row['state'],
            isFeatured: self::bool($row['is_featured'] ?? false),
            isUpcoming: EventSummaryDTO::isUpcomingAt($startsAt, $now),
            bannerFileId: self::nullIfEmpty($row['banner_file_id'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function detailFromRow(array $row): EventDetailDTO
    {
        $startsAt = self::parseDate($row['starts_at'] ?? null) ?? new DateTimeImmutable();
        $now = new DateTimeImmutable();
        $related = $this->loadRelated((string) $row['id'], $startsAt, $now);

        return new EventDetailDTO(
            id: (string) $row['id'],
            slug: (string) $row['slug'],
            title: (string) $row['title'],
            shortDescription: self::nullIfEmpty($row['short_description'] ?? null),
            description: self::nullIfEmpty($row['description'] ?? null),
            startsAt: $startsAt,
            endsAt: self::parseDate($row['ends_at'] ?? null),
            timezone: (string) $row['timezone'],
            venue: self::nullIfEmpty($row['venue'] ?? null),
            venueAddress: self::nullIfEmpty($row['venue_address'] ?? null),
            state: (string) $row['state'],
            isFeatured: self::bool($row['is_featured'] ?? false),
            isUpcoming: EventSummaryDTO::isUpcomingAt($startsAt, $now),
            bannerFileId: self::nullIfEmpty($row['banner_file_id'] ?? null),
            publishedAt: self::parseDate($row['published_at'] ?? null),
            completedAt: self::parseDate($row['completed_at'] ?? null),
            displayOrder: (int) ($row['display_order'] ?? 0),
            metadata: self::decodeJson($row['metadata'] ?? null),
            createdAt: self::parseDate($row['created_at'] ?? null) ?? new DateTimeImmutable(),
            related: $related,
        );
    }

    /**
     * Loads up to 4 related events: future-dated first (limit 2), then
     * most-recent past (limit 2). Excludes the event itself.
     *
     * @return list<EventSummaryDTO>
     */
    private function loadRelated(string $excludeId, DateTimeImmutable $startsAt, DateTimeImmutable $now): array
    {
        $related = [];
        $referenceNow = $now->format(DATE_ATOM);

        // Future-dated: starts_at >= now AND > this event's starts_at.
        $futureSql = 'SELECT '.self::COLUMNS.'
                      FROM   events
                      WHERE  deleted_at IS NULL
                        AND  state = \'published\'
                        AND  starts_at >= :now
                        AND  id <> :self
                        AND  starts_at <> :self_starts
                      ORDER BY starts_at ASC, id ASC
                      LIMIT 2';

        $result = $this->adapter->query($futureSql, [
            'now'         => $referenceNow,
            'self'        => $excludeId,
            'self_starts' => $startsAt->format(DATE_ATOM),
        ]);
        if (! $result->isFailure()) {
            foreach ($result->value() as $row) {
                $related[] = $this->summaryFromRow($row, $now);
            }
        }

        // Past: most recent first.
        if (count($related) < 4) {
            $pastSql = 'SELECT '.self::COLUMNS.'
                        FROM   events
                        WHERE  deleted_at IS NULL
                          AND  state IN (\'published\',\'completed\')
                          AND  starts_at < :now
                          AND  id <> :self
                        ORDER BY starts_at DESC, id DESC
                        LIMIT :remaining';

            $remaining = 4 - count($related);
            $result = $this->adapter->query($pastSql, [
                'now'       => $referenceNow,
                'self'      => $excludeId,
                'remaining' => $remaining,
            ]);
            if (! $result->isFailure()) {
                foreach ($result->value() as $row) {
                    $related[] = $this->summaryFromRow($row, $now);
                }
            }
        }

        return $related;
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
