<?php

declare(strict_types=1);

namespace App\Cms\Domain\Enums;

/**
 * The lifecycle state of a Static Page.
 *
 * Lifecycle: Draft → Published → Updated (transient marker) → Published
 * (fold-back) → Archived (terminal). D3 from cms-architecture.md locks
 * the semantics: Updated folds back into Published on the next save.
 *
 * String values match the PostgreSQL `static_page_state` enum defined in
 * `database/schema-neon/V1-schema.sql` line 82-87 exactly.
 */
enum StaticPageState: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case UPDATED = 'updated';
    case ARCHIVED = 'archived';

    /**
     * Whether this state is publicly readable on the website.
     * Published and Updated both expose body_html to public reads;
     * Draft and Archived do not.
     */
    public function isPubliclyReadable(): bool
    {
        return match ($this) {
            self::PUBLISHED, self::UPDATED => true,
            self::DRAFT, self::ARCHIVED => false,
        };
    }

    /**
     * Whether this state is terminal — no further transitions are
     * accepted. The state machine refuses all events from terminal
     * states with InvalidPageStateTransitionException.
     */
    public function isTerminal(): bool
    {
        return $this === self::ARCHIVED;
    }

    /**
     * Display label for admin UI surfaces.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PUBLISHED => 'Published',
            self::UPDATED => 'Updated',
            self::ARCHIVED => 'Archived',
        };
    }
}