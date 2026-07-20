<?php

declare(strict_types=1);

namespace App\Cms\Domain\Repositories;

use App\Cms\Domain\Entities\HeroBanner;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;

/**
 * Persistence boundary for the HeroBanner aggregate.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.5.2
 */
interface HeroBannerRepositoryContract
{
    public function findById(EntityId $id): ?HeroBanner;

    /**
     * @return list<HeroBanner>
     */
    public function listPublished(?int $limit = null): array;

    /**
     * Active = published AND (no date range OR within starts_at/ends_at).
     *
     * @return list<HeroBanner>
     */
    public function listActiveAt(DateTimeImmutable $when, ?int $limit = null): array;

    public function save(HeroBanner $banner): void;

    public function update(HeroBanner $banner): void;

    public function softDelete(EntityId $id): void;

    /**
     * @return list<HeroBanner>
     */
    public function listForPage(EntityId $pageId): array;

    public function attachToPage(EntityId $bannerId, EntityId $pageId, int $displayOrder): void;

    public function detachFromPage(EntityId $bannerId, EntityId $pageId): void;
}