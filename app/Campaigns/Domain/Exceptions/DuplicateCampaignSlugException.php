<?php

declare(strict_types=1);

namespace App\Campaigns\Domain\Exceptions;

use RuntimeException;

/**
 * DuplicateCampaignSlugException — raised when the campaigns.slug
 * partial unique index trips on insert/update.
 *
 * Carries the slug value so callers (typically FormRequest → controller)
 * can echo it back to the admin form for inline error display.
 *
 * Doctrine: the storage layer enforces uniqueness via the partial
 * unique index `campaigns_slug_live_idx`. The service layer translates
 * the resulting adapter error into this typed exception so the
 * controller layer can branch without string-matching.
 */
final class DuplicateCampaignSlugException extends RuntimeException
{
    public function __construct(
        public readonly string $slug,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('A campaign with slug "%s" already exists.', $slug),
            0,
            $previous,
        );
    }
}
