<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;

/**
 * Read-side carrier for `contact_information` rows.
 *
 * The CMS kernel does not modify contact points; admin UI (Phase 4)
 * does. In V1 contact points are seeded by migration / admin SQL.
 *
 * The contactType is a string (matches the PostgreSQL `contact_type`
 * enum from V1-schema.sql:152-159: address, phone, email, whatsapp,
 * social). Kept as string rather than a dedicated enum because V1
 * does not need to switch on it.
 */
final readonly class ContactPoint
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private readonly EntityId $id,
        private readonly string $label,
        private readonly string $contactType,
        private readonly string $value,
        private readonly bool $isPrimary,
        private readonly int $displayOrder,
        private readonly array $metadata = [],
    ) {
    }

    public function id(): EntityId
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function contactType(): string
    {
        return $this->contactType;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}