<?php

declare(strict_types=1);

namespace App\Campaigns\Domain\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * List-row DTO for public campaign reads.
 *
 * Returned by `CampaignsQueryContract::listDisplayable()` and
 * `listFeatured()`. Read-only, immutable, crosses the kernel boundary
 * to the UI layer (Sub-project 3).
 *
 * Doctrine: DTOs that cross kernel boundaries MUST be stable, read-only,
 * and minimal. They are part of the contract surface.
 *
 * State semantics: the V1 `campaign_state` enum is exposed as a string
 * ('active' | 'completed' | etc.); the `isActive` boolean is computed
 * on detail DTOs only.
 */
final readonly class CampaignSummaryDTO
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $shortDescription,
        public string $category,
        public string $currencyCode,
        public ?int $targetAmountMinor,
        public bool $isFeatured,
        public string $state,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public ?string $coverImageFileId,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('CampaignSummaryDTO id cannot be empty');
        }
        if ($slug === '') {
            throw new InvalidArgumentException('CampaignSummaryDTO slug cannot be empty');
        }
        if ($title === '') {
            throw new InvalidArgumentException('CampaignSummaryDTO title cannot be empty');
        }
        if ($category === '') {
            throw new InvalidArgumentException('CampaignSummaryDTO category cannot be empty');
        }
        if (strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException(
                "CampaignSummaryDTO currencyCode must be a 3-letter ISO 4217 code, got: {$currencyCode}"
            );
        }
        if ($targetAmountMinor !== null && $targetAmountMinor <= 0) {
            throw new InvalidArgumentException(
                "CampaignSummaryDTO targetAmountMinor must be positive when set, got: {$targetAmountMinor}"
            );
        }
        if ($state === '') {
            throw new InvalidArgumentException('CampaignSummaryDTO state cannot be empty');
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
            'category'            => $this->category,
            'currency_code'       => $this->currencyCode,
            'target_amount_minor' => $this->targetAmountMinor,
            'is_featured'         => $this->isFeatured,
            'state'               => $this->state,
            'starts_at'           => $this->startsAt?->format(DATE_ATOM),
            'ends_at'             => $this->endsAt?->format(DATE_ATOM),
            'cover_image_file_id' => $this->coverImageFileId,
        ];
    }
}
