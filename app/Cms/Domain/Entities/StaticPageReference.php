<?php

declare(strict_types=1);

namespace App\Cms\Domain\Entities;

use App\Cms\Domain\Enums\PageReferenceType;
use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Static Page Reference — junction row from `static_page_references`.
 *
 * Holds a single (page, target) linkage with display metadata. The
 * reference target is opaque to this entity — the type discriminator
 * tells the resolver which kernel to ask for the payload.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.3.3
 */
final class StaticPageReference implements EntityContract
{
    public const ENTITY_TYPE = 'static_page_reference';

    private function __construct(
        private readonly EntityId $id,
        private readonly EntityId $staticPageId,
        private readonly PageReferenceType $referenceType,
        private readonly EntityId $referenceId,
        private readonly int $displayOrder,
        private readonly ?string $context,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        EntityId $staticPageId,
        PageReferenceType $referenceType,
        EntityId $referenceId,
        int $displayOrder = 0,
        ?string $context = null,
        ?EntityId $id = null,
    ): self {
        if ($displayOrder < 0) {
            throw new InvalidArgumentException(
                "displayOrder cannot be negative (got {$displayOrder})"
            );
        }

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            staticPageId: $staticPageId,
            referenceType: $referenceType,
            referenceId: $referenceId,
            displayOrder: $displayOrder,
            context: $context,
            createdAt: new DateTimeImmutable(),
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = ['id', 'static_page_id', 'reference_type', 'reference_id', 'created_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("StaticPageReference row missing required key: {$key}");
            }
        }

        return new self(
            id: EntityId::fromString((string) $row['id']),
            staticPageId: EntityId::fromString((string) $row['static_page_id']),
            referenceType: PageReferenceType::from((string) $row['reference_type']),
            referenceId: EntityId::fromString((string) $row['reference_id']),
            displayOrder: (int) ($row['display_order'] ?? 0),
            context: isset($row['context']) ? (string) $row['context'] : null,
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
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
            'static_page_id' => $this->staticPageId->value(),
            'reference_type' => $this->referenceType->value,
            'reference_id' => $this->referenceId->value(),
            'display_order' => $this->displayOrder,
            'context' => $this->context,
            'created_at' => $this->createdAt->format(DATE_ATOM),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        $row = $this->toArray();
        $merged = array_merge($row, $changes);

        return self::fromRow($merged);
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function staticPageId(): EntityId
    {
        return $this->staticPageId;
    }

    public function referenceType(): PageReferenceType
    {
        return $this->referenceType;
    }

    public function referenceId(): EntityId
    {
        return $this->referenceId;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function context(): ?string
    {
        return $this->context;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
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