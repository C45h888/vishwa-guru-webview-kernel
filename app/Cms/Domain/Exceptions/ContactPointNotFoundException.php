<?php

declare(strict_types=1);

namespace App\Cms\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Thrown when a contact information row is requested but does not exist
 * (or has been soft-deleted). Reserved for the Phase 4 admin mutation
 * service; V1 is read-only and `findById` returns null on miss.
 */
final class ContactPointNotFoundException extends DomainException
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
            "Contact point not found with id: {$id}",
            id: $id,
        );
    }

    public function errorCode(): string
    {
        return 'cms.contact_information.not_found';
    }

    public function context(): array
    {
        return $this->id !== null ? ['id' => $this->id] : [];
    }
}