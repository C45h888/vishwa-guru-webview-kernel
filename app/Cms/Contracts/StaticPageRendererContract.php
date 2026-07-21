<?php

declare(strict_types=1);

namespace App\Cms\Contracts;

use App\Cms\Domain\DTOs\RenderedStaticPage;
use App\Cms\Domain\ValueObjects\PageSlug;

/**
 * Public read API for the CMS renderer.
 *
 * Implemented by `App\Cms\Services\StaticPageRendererService` and consumed
 * by `App\Http\Controllers\Public\*` via constructor injection. Kept as an
 * interface so controllers and feature tests can substitute a double.
 *
 * Doctrine (cms-architecture.md §7):
 *   - The producing kernel owns the contract.
 *   - Consumers never import the concrete service or its infrastructure.
 *   - `renderBySlug` returns null on missing/non-publicly-readable page;
 *     `renderHomepage` throws `StaticPageNotFoundException` on missing
 *     homepage row.
 */
interface StaticPageRendererContract
{
    /**
     * Render a published static page by slug, with hero banners and
     * resolved references. Returns null when the page does not exist
     * or is not in a publicly-readable state.
     */
    public function renderBySlug(PageSlug $slug): ?RenderedStaticPage;

    /**
     * Render the homepage. Throws `StaticPageNotFoundException` when
     * no homepage row exists in the database.
     */
    public function renderHomepage(): RenderedStaticPage;
}
