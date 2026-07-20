<?php

declare(strict_types=1);

namespace App\Cms\Domain\Repositories;

use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Persistence\ValueObjects\EntityId;

/**
 * Persistence boundary for the StaticPage aggregate.
 *
 * Implementations are owned by the CMS Infrastructure layer. The
 * StaticPage domain depends only on this contract. Soft-deleted pages
 * are invisible to public reads; admin reads (Phase 4) opt in via a
 * dedicated method.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.5.1
 */
interface StaticPageRepositoryContract
{
    public function findById(EntityId $id): ?StaticPage;

    public function findBySlug(PageSlug $slug): ?StaticPage;

    public function findHomepage(): ?StaticPage;

    /**
     * @return list<StaticPage>
     */
    public function listPublished(?int $limit = null, ?int $offset = null): array;

    /**
     * @return list<StaticPage>
     */
    public function listByState(StaticPageState $state, ?int $limit = null): array;

    /**
     * @return list<StaticPage>
     */
    public function listAll(?int $limit = null, ?int $offset = null): array;

    public function save(StaticPage $page): void;

    public function update(StaticPage $page): void;

    public function softDelete(EntityId $id): void;

    public function existsBySlug(PageSlug $slug, ?EntityId $excludeId = null): bool;

    public function lockBySlugForUpdate(PageSlug $slug): ?StaticPage;

    public function lockByIdForUpdate(EntityId $id): ?StaticPage;

    public function countByState(StaticPageState $state): int;
}