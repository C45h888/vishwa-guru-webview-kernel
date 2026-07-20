<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Domain\Entities\StaticPage;
use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\Repositories\StaticPageRepositoryContract;
use App\Cms\Domain\ValueObjects\PageSlug;
use App\Persistence\ValueObjects\EntityId;

/**
 * StaticPageQueryService — read-side API for the CMS kernel.
 *
 * Phase 3 frontend uses this for navigation and direct lookups. The
 * cache-aware render path lives in StaticPageRendererService.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §4.2
 */
final class StaticPageQueryService
{
    public function __construct(
        private readonly StaticPageRepositoryContract $pages,
    ) {
    }

    public function findBySlug(PageSlug $slug): ?StaticPage
    {
        return $this->pages->findBySlug($slug);
    }

    public function findById(EntityId $id): ?StaticPage
    {
        return $this->pages->findById($id);
    }

    public function findHomepage(): ?StaticPage
    {
        return $this->pages->findHomepage();
    }

    /**
     * @return list<StaticPage>
     */
    public function listPublishedNavigation(?int $limit = null): array
    {
        return $this->pages->listPublished($limit);
    }

    /**
     * @return list<StaticPage>
     */
    public function searchByTitlePrefix(string $prefix, int $limit = 25): array
    {
        $published = $this->pages->listByState(StaticPageState::PUBLISHED, $limit * 2);
        $matched = [];
        foreach ($published as $page) {
            if (stripos($page->title(), $prefix) === 0) {
                $matched[] = $page;
                if (count($matched) >= $limit) {
                    break;
                }
            }
        }

        return $matched;
    }
}