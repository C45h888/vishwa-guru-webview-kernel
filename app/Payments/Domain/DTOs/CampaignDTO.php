<?php

declare(strict_types=1);

namespace App\Payments\Domain\DTOs;

use InvalidArgumentException;

/**
 * Read-only value object for cross-kernel campaign reads.
 *
 * Returned by `CampaignQueryContract::findActiveById()`. Carries only the
 * fields the CMS reference renderer needs — does NOT leak the full
 * campaigns row (no description, no metadata blob, no audit timestamps).
 *
 * Doctrine (cms-architecture.md §7):
 *   - DTOs that cross kernel boundaries MUST be stable, read-only, and
 *     minimal. They are part of the contract surface.
 */
final readonly class CampaignDTO
{
    public function __construct(
        private string $id,
        private string $title,
        private string $slug,
        private string $currencyCode,
        private ?int $targetAmountMinor,
        private bool $isActive,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('CampaignDTO id cannot be empty');
        }
        if ($title === '') {
            throw new InvalidArgumentException('CampaignDTO title cannot be empty');
        }
        if (strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException(
                "CampaignDTO currencyCode must be a 3-letter ISO 4217 code, got: {$currencyCode}"
            );
        }
        if ($targetAmountMinor !== null && $targetAmountMinor <= 0) {
            throw new InvalidArgumentException(
                "CampaignDTO targetAmountMinor must be positive when set, got: {$targetAmountMinor}"
            );
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function targetAmountMinor(): ?int
    {
        return $this->targetAmountMinor;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'slug'                => $this->slug,
            'currency_code'       => $this->currencyCode,
            'target_amount_minor' => $this->targetAmountMinor,
            'is_active'           => $this->isActive,
        ];
    }
}