<?php

declare(strict_types=1);

namespace App\Cms\Domain\Entities;

use App\Cms\Domain\Enums\StaticPageState;
use App\Cms\Domain\Enums\CmsTransitionEvent;
use App\Cms\Domain\Exceptions\InvalidPageStateTransitionException;
use App\Cms\Domain\StateMachines\StaticPageStateMachine;
use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * Hero Banner entity.
 *
 * Mirrors the `hero_banners` table defined in
 * `database/schema-neon/V1-schema.sql` lines 299-319. Hero banners share
 * the `static_page_state` enum (DRAFT / PUBLISHED / UPDATED / ARCHIVED)
 * and the StaticPageStateMachine — they're CMS-managed assets.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §3.3.2
 */
final class HeroBanner implements EntityContract
{
    public const ENTITY_TYPE = 'hero_banner';

    private function __construct(
        private readonly EntityId $id,
        private readonly ?string $title,
        private readonly ?string $subtitle,
        private readonly ?string $ctaLabel,
        private readonly ?string $ctaUrl,
        private readonly ?EntityId $imageFileId,
        private readonly ?EntityId $mobileImageFileId,
        private readonly StaticPageState $state,
        private readonly int $displayOrder,
        private readonly ?DateTimeImmutable $startsAt,
        private readonly ?DateTimeImmutable $endsAt,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $deletedAt = null,
        private readonly ?string $createdBy = null,
        private readonly ?string $updatedBy = null,
    ) {
    }

    public static function create(
        ?string $title,
        ?string $subtitle,
        ?string $ctaLabel,
        ?string $ctaUrl,
        ?EntityId $imageFileId,
        ?EntityId $mobileImageFileId,
        int $displayOrder,
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        ?string $createdBy = null,
        ?EntityId $id = null,
    ): self {
        if ($startsAt !== null && $endsAt !== null && $endsAt < $startsAt) {
            throw new InvalidArgumentException(
                "HeroBanner endsAt must be >= startsAt"
            );
        }
        if ($displayOrder < 0) {
            throw new InvalidArgumentException(
                "HeroBanner displayOrder cannot be negative (got {$displayOrder})"
            );
        }

        $now = new DateTimeImmutable();

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            title: $title,
            subtitle: $subtitle,
            ctaLabel: $ctaLabel,
            ctaUrl: $ctaUrl,
            imageFileId: $imageFileId,
            mobileImageFileId: $mobileImageFileId,
            state: StaticPageState::DRAFT,
            displayOrder: $displayOrder,
            startsAt: $startsAt,
            endsAt: $endsAt,
            createdAt: $now,
            updatedAt: $now,
            deletedAt: null,
            // Drive-by C: spec §9.3 — see StaticPage::draft() rationale.
            createdBy: $createdBy ?? 'system',
            updatedBy: 'system',
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = ['id', 'state', 'created_at', 'updated_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("HeroBanner row missing required key: {$key}");
            }
        }

        return new self(
            id: EntityId::fromString((string) $row['id']),
            title: isset($row['title']) ? (string) $row['title'] : null,
            subtitle: isset($row['subtitle']) ? (string) $row['subtitle'] : null,
            ctaLabel: isset($row['cta_label']) ? (string) $row['cta_label'] : null,
            ctaUrl: isset($row['cta_url']) ? (string) $row['cta_url'] : null,
            imageFileId: isset($row['image_file_id']) && $row['image_file_id'] !== null
                ? EntityId::fromString((string) $row['image_file_id'])
                : null,
            mobileImageFileId: isset($row['mobile_image_file_id']) && $row['mobile_image_file_id'] !== null
                ? EntityId::fromString((string) $row['mobile_image_file_id'])
                : null,
            state: StaticPageState::from((string) $row['state']),
            displayOrder: (int) ($row['display_order'] ?? 0),
            startsAt: self::parseDate($row['starts_at'] ?? null),
            endsAt: self::parseDate($row['ends_at'] ?? null),
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
            updatedAt: self::parseDate($row['updated_at']) ?? new DateTimeImmutable(),
            deletedAt: self::parseDate($row['deleted_at'] ?? null),
            createdBy: isset($row['created_by']) ? (string) $row['created_by'] : null,
            updatedBy: isset($row['updated_by']) ? (string) $row['updated_by'] : null,
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
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'cta_label' => $this->ctaLabel,
            'cta_url' => $this->ctaUrl,
            'image_file_id' => $this->imageFileId?->value(),
            'mobile_image_file_id' => $this->mobileImageFileId?->value(),
            'state' => $this->state->value,
            'display_order' => $this->displayOrder,
            'starts_at' => $this->startsAt?->format(DATE_ATOM),
            'ends_at' => $this->endsAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        if (array_key_exists('state', $changes)) {
            throw new LogicException(
                'HeroBanner::withChanges() cannot set state directly. Use transitionTo() with a StaticPageStateMachine.'
            );
        }

        $row = $this->toArray();
        $merged = array_merge($row, $changes);
        $merged['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($merged);
    }

    public function transitionTo(
        StaticPageStateMachine $machine,
        StaticPageState $to,
        array $context = [],
    ): self {
        $event = match ($to) {
            StaticPageState::PUBLISHED => CmsTransitionEvent::PAGE_PUBLISHED,
            StaticPageState::UPDATED   => CmsTransitionEvent::PAGE_EDITED,
            StaticPageState::ARCHIVED  => CmsTransitionEvent::PAGE_ARCHIVED,
            default => throw new InvalidPageStateTransitionException(
                sprintf('HeroBanner cannot transition to %s', $to->value),
                $this->state,
                $to,
            ),
        };

        $result = $machine->transition($this->state, $event, $context);

        $row = $this->toArray();
        $row['state'] = $result->toState()->value;
        foreach ($result->entityChanges() as $field => $value) {
            $row[$field] = $value;
        }
        foreach ($result->timestampChanges() as $column => $ts) {
            $row[$column] = $ts->format(DATE_ATOM);
        }
        $row['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($row);
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function title(): ?string
    {
        return $this->title;
    }

    public function subtitle(): ?string
    {
        return $this->subtitle;
    }

    public function ctaLabel(): ?string
    {
        return $this->ctaLabel;
    }

    public function ctaUrl(): ?string
    {
        return $this->ctaUrl;
    }

    public function imageFileId(): ?EntityId
    {
        return $this->imageFileId;
    }

    public function mobileImageFileId(): ?EntityId
    {
        return $this->mobileImageFileId;
    }

    public function state(): StaticPageState
    {
        return $this->state;
    }

    public function displayOrder(): int
    {
        return $this->displayOrder;
    }

    public function startsAt(): ?DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
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

    public function createdBy(): ?string
    {
        return $this->createdBy;
    }

    public function updatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function isActiveAt(DateTimeImmutable $when): bool
    {
        if ($this->state !== StaticPageState::PUBLISHED) {
            return false;
        }
        if ($this->startsAt !== null && $when < $this->startsAt) {
            return false;
        }
        if ($this->endsAt !== null && $when > $this->endsAt) {
            return false;
        }

        return true;
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