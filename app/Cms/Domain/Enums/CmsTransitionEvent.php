<?php

declare(strict_types=1);

namespace App\Cms\Domain\Enums;

/**
 * The transition vocabulary consumed by StaticPageStateMachine.
 *
 * Each event triggers a (from, event) → target transition through the
 * state machine. The machine accepts only events from its known
 * vocabulary; unknown events are rejected with
 * InvalidPageStateTransitionException.
 *
 * This enum is the CMS kernel's own vocabulary — distinct from the
 * Payments kernel's `StateTransitionEvent` enum (which carries a broader
 * vocabulary for Payment/Donation/Receipt transitions). Keeping the CMS
 * vocabulary local preserves kernel independence per the project's
 * architectural doctrine ("Business entities communicate through services
 * rather than direct coupling" — domain-modules.md:586).
 */
enum CmsTransitionEvent: string
{
    case PAGE_PUBLISHED = 'page_published';
    case PAGE_EDITED = 'page_edited';
    case PAGE_ARCHIVED = 'page_archived';
    case PAGE_RESTORED = 'page_restored';

    /**
     * Display label for admin UI surfaces.
     */
    public function label(): string
    {
        return match ($this) {
            self::PAGE_PUBLISHED => 'Page published',
            self::PAGE_EDITED => 'Page edited',
            self::PAGE_ARCHIVED => 'Page archived',
            self::PAGE_RESTORED => 'Page restored',
        };
    }
}