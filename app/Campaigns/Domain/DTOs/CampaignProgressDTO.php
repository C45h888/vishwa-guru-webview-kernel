<?php

declare(strict_types=1);

namespace App\Campaigns\Domain\DTOs;

use InvalidArgumentException;

/**
 * Per-currency campaign progress rollup.
 *
 * Returned by `CampaignsQueryContract::progressFor()`. ONE row per
 * `currency_code` — amounts are NEVER mixed across currencies.
 *
 * Doctrine (AGENTS.md §13 + cms-architecture.md): money is BIGINT minor
 * units paired with CHAR(3) ISO currency. Aggregations MUST group by
 * currency_code. Consumers receive one row each per currency in the
 * donations table for this campaign.
 */
final readonly class CampaignProgressDTO
{
    public function __construct(
        public string $campaignId,
        public string $currencyCode,
        public int $raisedAmountMinor,
        public int $donationCount,
        public int $distinctDonorCount,
    ) {
        if ($campaignId === '') {
            throw new InvalidArgumentException('CampaignProgressDTO campaignId cannot be empty');
        }
        if (strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException(
                "CampaignProgressDTO currencyCode must be a 3-letter ISO 4217 code, got: {$currencyCode}"
            );
        }
        if ($raisedAmountMinor < 0) {
            throw new InvalidArgumentException(
                "CampaignProgressDTO raisedAmountMinor cannot be negative, got: {$raisedAmountMinor}"
            );
        }
        if ($donationCount < 0) {
            throw new InvalidArgumentException(
                "CampaignProgressDTO donationCount cannot be negative, got: {$donationCount}"
            );
        }
        if ($distinctDonorCount < 0) {
            throw new InvalidArgumentException(
                "CampaignProgressDTO distinctDonorCount cannot be negative, got: {$distinctDonorCount}"
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'campaign_id'           => $this->campaignId,
            'currency_code'         => $this->currencyCode,
            'raised_amount_minor'   => $this->raisedAmountMinor,
            'donation_count'        => $this->donationCount,
            'distinct_donor_count'  => $this->distinctDonorCount,
        ];
    }
}
