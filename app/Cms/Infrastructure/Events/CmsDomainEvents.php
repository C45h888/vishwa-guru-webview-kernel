<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Events;

/**
 * Domain event name constants for the CMS kernel.
 *
 * Service methods dispatch via Laravel's event() helper. The
 * CacheInvalidationListener listens to these and invalidates the
 * resolved-page cache. Audit recorder (later pass) also subscribes.
 *
 * Naming convention: `cms.<aggregate>.<event>`.
 */
final class CmsDomainEvents
{
    public const STATIC_PAGE_PUBLISHED = 'cms.static_page.published';
    public const STATIC_PAGE_UPDATED = 'cms.static_page.updated';
    public const STATIC_PAGE_ARCHIVED = 'cms.static_page.archived';
    public const STATIC_PAGE_DELETED = 'cms.static_page.deleted';
    public const STATIC_PAGE_BODY_CHANGED = 'cms.static_page.body_changed';
    public const HOMEPAGE_CHANGED = 'cms.static_page.homepage_changed';
    public const HERO_BANNER_CHANGED = 'cms.hero_banner.changed';
    public const REFERENCE_ATTACHED = 'cms.reference.attached';
    public const REFERENCE_DETACHED = 'cms.reference.detached';

    private function __construct()
    {
        // Static-only event name registry
    }
}