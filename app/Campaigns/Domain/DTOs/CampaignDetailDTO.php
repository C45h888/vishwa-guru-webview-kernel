<?php

declare(strict_types=1);

namespace App\Campaigns\Domain\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Full-detail DTO for the public campaign page.
 *
 * Returned by `CampaignsQueryContract::findById()` and `findBySlug()`.
 * Extends the summary surface with the long description, metadata blob,
 * audit timestamps, and a computed `isActive` boolean.
 *
 * Doctrine: DTOs that cross kernel boundaries MUST be stable, read-only,
 * and minimal. They are part of the contract surface.
 */
final readonly class CampaignDetailDTO
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $shortDescription,
        public ?string $description,
        public string $category,
        public string $currencyCode,
        public ?int $targetAmountMinor,
        public bool $isFeatured,
        public bool $isActive,
        public string $state,
        public int $displayOrder,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public ?string $coverImageFileId,
        /** @var array<string, mixed> */
        public array $metadata,
        public DateTimeImmutable $createdAt,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('CampaignDetailDTO id cannot be empty');
        }
        if ($slug === '') {
            throw new InvalidArgumentException('CampaignDetailDTO slug cannot be empty');
        }
        if ($title === '') {
            throw new InvalidArgumentException('CampaignDetailDTO title cannot be empty');
        }
        if (strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException(
                "CampaignDetailDTO currencyCode must be a 3-letter ISO 4217 code, got: {$currencyCode}"
            );
        }
        if ($targetAmountMinor !== null && $targetAmountMinor <= 0) {
            throw new InvalidArgumentException(
                "CampaignDetailDTO targetAmountMinor must be positive when set, got: {$targetAmountMinor}"
            );
        }
        if ($state === '') {
            throw new InvalidArgumentException('CampaignDetailDTO state cannot be empty');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'slug'                => $this->slug,
            'title'               => $this->title,
            'short_description'   => $this->shortDescription,
            'description'         => $this->description,
            'category'            => $this->category,
            'currency_code'       => $this->currencyCode,
            'target_amount_minor' => $this->targetAmountMinor,
            'is_featured'         => $this->isFeatured,
            'is_active'           => $this->isActive,
            'state'               => $this->state,
            'display_order'       => $this->displayOrder,
            'starts_at'           => $this->startsAt?->format(DATE_ATOM),
            'ends_at'             => $this->endsAt?->format(DATE_ATOM),
            'cover_image_file_id' => $this->coverImageFileId,
            'metadata'            => $this->metadata,
            'created_at'          => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
