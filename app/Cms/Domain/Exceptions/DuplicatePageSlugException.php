<?php

declare(strict_types=1);

namespace App\Cms\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a slug is already in use by another live (non-soft-
 * deleted) static page. The kernel performs a service-level pre-check
 * before INSERT and the DB partial unique index is the safety net for
 * the race window.
 */
final class DuplicatePageSlugException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $slug,
        private readonly ?string $existingPageId = null,
    ) {
        parent::__construct($message);
    }

    public static function forSlug(string $slug, ?string $existingPageId = null): self
    {
        return new self(
            sprintf('Static page slug already in use: %s', $slug),
            slug: $slug,
            existingPageId: $existingPageId,
        );
    }

    public function errorCode(): string
    {
        return 'cms.static_page.slug.duplicate';
    }

    public function context(): array
    {
        return array_filter([
            'slug' => $this->slug,
            'existing_page_id' => $this->existingPageId,
        ], static fn ($v) => $v !== null);
    }
}