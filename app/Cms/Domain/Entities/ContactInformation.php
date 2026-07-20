<?php

declare(strict_types=1);

namespace App\Cms\Domain\Entities;

use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Contact Information entity.
 *
 * Mirrors the `contact_information` table from
 * `database/schema-neon/V1-schema.sql` lines 362-375. Read-only in V1 —
 * no state machine, admin SQL mutation only. Phase 4 adds the admin
 * mutation API.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.3.4
 */
final class ContactInformation implements EntityContract
{
    public const ENTITY_TYPE = 'contact_information';

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly EntityId $id,
        private readonly string $label,
        private readonly string $contactType,
        private readonly string $value,
        private readonly bool $isPrimary,
        private readonly int $displayOrder,
        private readonly array $metadata,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $deletedAt = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        string $label,
        string $contactType,
        string $value,
        bool $isPrimary = false,
        int $displayOrder = 0,
        array $metadata = [],
        ?EntityId $id = null,
    ): self {
        if (trim($label) === '') {
            throw new InvalidArgumentException('ContactInformation label cannot be empty');
        }
        if (trim($contactType) === '') {
            throw new InvalidArgumentException('ContactInformation contactType cannot be empty');
        }
        if (trim($value) === '') {
            throw new InvalidArgumentException('ContactInformation value cannot be empty');
        }
        if ($displayOrder < 0) {
            throw new InvalidArgumentException(
                "displayOrder cannot be negative (got {$displayOrder})"
            );
        }

        $now = new DateTimeImmutable();

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            label: $label,
            contactType: $contactType,
            value: $value,
            isPrimary: $isPrimary,
            displayOrder: $displayOrder,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now,
            deletedAt: null,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = ['id', 'label', 'contact_type', 'value', 'created_at', 'updated_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("ContactInformation row missing required key: {$key}");
            }
        }

        $metadata = [];
        if (isset($row['metadata'])) {
            $raw = is_string($row['metadata'])
                ? json_decode((string) $row['metadata'], true, 512, JSON_THROW_ON_ERROR)
                : $row['metadata'];
            if (is_array($raw)) {
                $metadata = $raw;
            }
        }

        return new self(
            id: EntityId::fromString((string) $row['id']),
            label: (string) $row['label'],
            contactType: (string) $row['contact_type'],
            value: (string) $row['value'],
            isPrimary: (bool) ($row['is_primary'] ?? false),
            displayOrder: (int) ($row['display_order'] ?? 0),
            metadata: $metadata,
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
            updatedAt: self::parseDate($row['updated_at']) ?? new DateTimeImmutable(),
            deletedAt: self::parseDate($row['deleted_at'] ?? null),
        );
    }

    public function id(): EntityId
    {
        return $this->id;
    }

    public function entityType(): string
    {
        return self::ENTITY_TYPE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'label' => $this->label,
            'contact_type' => $this->contactType,
            'value' => $this->value,
            'is_primary' => $this->isPrimary,
            'display_order' => $this->displayOrder,
            'metadata' => json_encode($this->metadata, JSON_THROW_ON_ERROR),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        $row = $this->toArray();
        $merged = array_merge($row, $changes);
        $merged['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($merged);
    }

    // ─── Getters ────────────────────────────────────────────────────────

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

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value);
    }
}