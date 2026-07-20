<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters;

use App\Payments\Contracts\CampaignQueryContract;
use App\Payments\Domain\DTOs\CampaignDTO;
use App\Persistence\Contracts\PersistenceAdapterContract;

/**
 * Eloquent-style adapter that fulfills the Shape A bridge contract
 * `CampaignQueryContract` by reading from the `campaigns` table via
 * the kernel-owned `PersistenceAdapterContract`.
 *
 * Doctrine:
 *   - Never touches `DB::` facade. Cross-kernel contracts read through
 *     the shared persistence kernel.
 *   - Returns null/false (never throws) when the campaign is missing,
 *     soft-deleted, or in a non-displayable state — CMS callers degrade.
 *   - "Displayable" = not soft-deleted AND state in {active, completed}.
 *     Archived/draft campaigns are hidden from public rendering.
 */
final class CampaignQueryAdapter implements CampaignQueryContract
{
    private const DISPLAYABLE_STATES = ['active', 'completed'];

    public function __construct(
        private readonly PersistenceAdapterContract $persistence,
    ) {}

    public function isDisplayable(string $campaignId): bool
    {
        return $this->findActiveById($campaignId) !== null;
    }

    public function findActiveById(string $campaignId): ?CampaignDTO
    {
        if ($campaignId === '') {
            return null;
        }

        $result = $this->persistence->query(
            "SELECT id, title, slug, currency_code, target_amount_minor, state, deleted_at
             FROM campaigns
             WHERE id = :id
             LIMIT 1",
            ['id' => $campaignId],
        );

        if ($result->isFailure()) {
            return null;
        }

        $rows = $result->value();
        if (empty($rows)) {
            return null;
        }

        $row = $rows[0];

        // Soft-deleted → not displayable.
        if (! empty($row['deleted_at'])) {
            return null;
        }

        // State filter — only active/completed campaigns are public.
        if (! in_array($row['state'] ?? '', self::DISPLAYABLE_STATES, true)) {
            return null;
        }

        try {
            return new CampaignDTO(
                id: (string) $row['id'],
                title: (string) $row['title'],
                slug: (string) $row['slug'],
                currencyCode: (string) $row['currency_code'],
                targetAmountMinor: $row['target_amount_minor'] !== null
                    ? (int) $row['target_amount_minor']
                    : null,
                isActive: ($row['state'] ?? '') === 'active',
            );
        } catch (\InvalidArgumentException) {
            // Row present but malformed — treat as not displayable rather
            // than throwing across the kernel boundary.
            return null;
        }
    }
}