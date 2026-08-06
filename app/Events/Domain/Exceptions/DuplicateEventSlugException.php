<?php

declare(strict_types=1);

namespace App\Events\Domain\Exceptions;

use RuntimeException;

/**
 * DuplicateEventSlugException — raised when the events.slug partial
 * unique index trips on insert/update.
 *
 * Carries the slug value so callers (typically FormRequest → controller)
 * can echo it back to the admin for inline error display.
 *
 * Doctrine: the storage layer enforces uniqueness via the partial
 * unique index `events_slug_live_idx`. The service layer translates
 * the resulting adapter error into this typed exception so the
 * controller layer can branch without string-matching.
 */
final class DuplicateEventSlugException extends RuntimeException
{
    public function __construct(
        public readonly string $slug,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('An event with slug "%s" already exists.', $slug),
            0,
            $previous,
        );
    }
}
