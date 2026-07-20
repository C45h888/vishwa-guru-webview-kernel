<?php

declare(strict_types=1);

namespace App\Cms\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a hero banner is referenced but does not exist (or has
 * been soft-deleted). The kernel never throws this directly; services
 * wrap missing-banner checks and surface the exception via
 * `Result::failure('cms.hero_banner.not_found')`.
 */
final class HeroBannerNotFoundException extends DomainException
{
    public function __construct(
        string $message,
        private readonly ?string $id = null,
    ) {
        parent::__construct($message);
    }

    public static function byId(string $id): self
    {
        return new self(
            "Hero banner not found with id: {$id}",
            id: $id,
        );
    }

    public function errorCode(): string
    {
        return 'cms.hero_banner.not_found';
    }

    public function context(): array
    {
        return $this->id !== null ? ['id' => $this->id] : [];
    }
}