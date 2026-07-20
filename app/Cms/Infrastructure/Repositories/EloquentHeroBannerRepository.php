<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Repositories;

use App\Cms\Domain\Entities\HeroBanner;
use App\Cms\Domain\Repositories\HeroBannerRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use RuntimeException;

/**
 * Eloquent implementation of HeroBannerRepositoryContract.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §5.1
 *
 * @implements HeroBannerRepositoryContract
 */
final class EloquentHeroBannerRepository implements HeroBannerRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    public function findById(EntityId $id): ?HeroBanner
    {
        $result = $this->adapter->query(
            'SELECT * FROM hero_banners WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return HeroBanner::fromRow($result->value()[0]);
    }

    public function listPublished(?int $limit = null): array
    {
        $sql = 'SELECT * FROM hero_banners WHERE state = :state AND deleted_at IS NULL ORDER BY display_order ASC';
        $params = ['state' => 'published'];
        if ($limit !== null) {
            $sql .= ' LIMIT :lim';
            $params['lim'] = $limit;
        }

        return $this->fetchList($sql, $params);
    }

    public function listActiveAt(DateTimeImmutable $when, ?int $limit = null): array
    {
        $sql = 'SELECT * FROM hero_banners
                WHERE state = :state
                  AND deleted_at IS NULL
                  AND (starts_at IS NULL OR starts_at <= :when)
                  AND (ends_at IS NULL OR ends_at >= :when)
                ORDER BY display_order ASC';
        $params = ['state' => 'published', 'when' => $when->format(DATE_ATOM)];
        if ($limit !== null) {
            $sql .= ' LIMIT :lim';
            $params['lim'] = $limit;
        }

        return $this->fetchList($sql, $params);
    }

    public function save(HeroBanner $banner): void
    {
        $row = $banner->toArray();
        $sql = 'INSERT INTO hero_banners (
            id, title, subtitle, cta_label, cta_url,
            image_file_id, mobile_image_file_id, state, display_order,
            starts_at, ends_at, created_at, updated_at,
            deleted_at, created_by, updated_by
        ) VALUES (
            :id, :title, :subtitle, :cta_label, :cta_url,
            :image_file_id, :mobile_image_file_id, :state, :display_order,
            :starts_at, :ends_at, :created_at, :updated_at,
            :deleted_at, :created_by, :updated_by
        )';

        $exec = $this->adapter->execute($sql, $this->rowToParams($row));
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentHeroBannerRepository::save failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function update(HeroBanner $banner): void
    {
        $row = $banner->toArray();
        $sql = 'UPDATE hero_banners SET
            title = :title,
            subtitle = :subtitle,
            cta_label = :cta_label,
            cta_url = :cta_url,
            image_file_id = :image_file_id,
            mobile_image_file_id = :mobile_image_file_id,
            state = :state,
            display_order = :display_order,
            starts_at = :starts_at,
            ends_at = :ends_at,
            updated_at = :updated_at,
            deleted_at = :deleted_at,
            updated_by = :updated_by
        WHERE id = :id';

        $params = $this->rowToParams($row);
        $params['id'] = $row['id'];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentHeroBannerRepository::update failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function softDelete(EntityId $id): void
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $exec = $this->adapter->execute(
            'UPDATE hero_banners SET deleted_at = :now WHERE id = :id',
            ['id' => $id->value(), 'now' => $now],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentHeroBannerRepository::softDelete failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function listForPage(EntityId $pageId): array
    {
        $result = $this->adapter->query(
            'SELECT hb.* FROM hero_banners hb
             JOIN hero_banner_pages hbp ON hb.id = hbp.hero_banner_id
             WHERE hbp.static_page_id = :pid AND hb.deleted_at IS NULL
             ORDER BY hbp.display_order ASC',
            ['pid' => $pageId->value()],
        );
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row) => HeroBanner::fromRow($row),
            $result->value(),
        );
    }

    public function attachToPage(EntityId $bannerId, EntityId $pageId, int $displayOrder): void
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $exec = $this->adapter->execute(
            'INSERT INTO hero_banner_pages (hero_banner_id, static_page_id, display_order, created_at)
             VALUES (:bid, :pid, :ord, :now)
             ON CONFLICT (hero_banner_id, static_page_id)
             DO UPDATE SET display_order = EXCLUDED.display_order',
            ['bid' => $bannerId->value(), 'pid' => $pageId->value(), 'ord' => $displayOrder, 'now' => $now],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentHeroBannerRepository::attachToPage failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    public function detachFromPage(EntityId $bannerId, EntityId $pageId): void
    {
        $exec = $this->adapter->execute(
            'DELETE FROM hero_banner_pages WHERE hero_banner_id = :bid AND static_page_id = :pid',
            ['bid' => $bannerId->value(), 'pid' => $pageId->value()],
        );
        if ($exec->isFailure()) {
            throw new RuntimeException(
                'EloquentHeroBannerRepository::detachFromPage failed: '.($exec->error() ?? 'unknown')
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowToParams(array $row): array
    {
        return [
            'id' => $row['id'],
            'title' => $row['title'],
            'subtitle' => $row['subtitle'],
            'cta_label' => $row['cta_label'],
            'cta_url' => $row['cta_url'],
            'image_file_id' => $row['image_file_id'],
            'mobile_image_file_id' => $row['mobile_image_file_id'],
            'state' => $row['state'],
            'display_order' => $row['display_order'],
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
            'created_by' => $row['created_by'],
            'updated_by' => $row['updated_by'],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<HeroBanner>
     */
    private function fetchList(string $sql, array $params): array
    {
        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            return [];
        }

        return array_map(
            static fn (array $row) => HeroBanner::fromRow($row),
            $result->value(),
        );
    }
}