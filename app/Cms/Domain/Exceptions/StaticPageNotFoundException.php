<?php

declare(strict_types=1);

namespace App\Cms\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a static page is requested but does not exist (or has been
 * soft-deleted). The CMS kernel never throws this directly; services
 * wrap missing-entity checks and surface the exception via
 * `Result::failure('cms.static_page.not_found')`.
 */
final class StaticPageNotFoundException extends DomainException
{
    public function __construct(
        string $message,
        private readonly ?string $slug = null,
        private readonly ?string $id = null,
    ) {
        parent::__construct($message);
    }

    public static function bySlug(string $slug): self
    {
        return new self(
            "Static page not found with slug: {$slug}",
            slug: $slug,
        );
    }

    public static function byId(string $id): self
    {
        return new self(
            "Static page not found with id: {$id}",
            id: $id,
        );
    }

    public function errorCode(): string
    {
        return 'cms.static_page.not_found';
    }

    public function context(): array
    {
        return array_filter([
            'slug' => $this->slug,
            'id' => $this->id,
        ], static fn ($v) => $v !== null);
    }
}