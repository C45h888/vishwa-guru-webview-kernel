<?php

declare(strict_types=1);

namespace App\Cms\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when an attempt is made to mark a second page as the homepage
 * while another page already holds the homepage status. The DB EXCLUDE
 * constraint `static_pages_single_homepage` enforces this at the schema
 * level; this exception is the application-level translation.
 *
 * Recoverable: retry with a different target, or explicitly clear the
 * existing homepage first.
 */
final class HomepageAlreadyAssignedException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $currentHomepageId,
        private readonly string $attemptedHomepageId,
    ) {
        parent::__construct($message);
    }

    public static function between(string $currentHomepageId, string $attemptedHomepageId): self
    {
        return new self(
            sprintf(
                'Cannot assign homepage: page %s is already the homepage',
                $currentHomepageId,
            ),
            currentHomepageId: $currentHomepageId,
            attemptedHomepageId: $attemptedHomepageId,
        );
    }

    public function errorCode(): string
    {
        return 'cms.static_page.homepage.conflict';
    }

    public function context(): array
    {
        return [
            'current_homepage_id' => $this->currentHomepageId,
            'attempted_homepage_id' => $this->attemptedHomepageId,
        ];
    }
}