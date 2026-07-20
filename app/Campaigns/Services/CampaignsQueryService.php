<?php

declare(strict_types=1);

namespace App\Campaigns\Services;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Campaigns\Domain\DTOs\CampaignDetailDTO;
use App\Campaigns\Domain\DTOs\CampaignPagedResultDTO;
use App\Campaigns\Domain\DTOs\CampaignProgressDTO;
use App\Campaigns\Domain\DTOs\CampaignSummaryDTO;
use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use RuntimeException;

/**
 * CampaignsQueryService — public read surface for temple campaigns.
 *
 * Composes CampaignRepositoryContract (campaigns table) with an
 * inline donations rollup (donations table, grouped by currency_code)
 * to satisfy `progressFor()`. No mutations; V1 is read-only.
 *
 * Doctrine: thin service. Composes the repo, clamps inputs, and
 * delegates. Money aggregation MUST group by currency_code — one
 * CampaignProgressDTO per currency present in the donations table.
 *
 * Page limits: perPage is clamped to [1, 50] to prevent runaway
 * queries; default 12. Page is clamped to >= 1.
 */
final class CampaignsQueryService implements CampaignsQueryContract
{
    private const DEFAULT_PER_PAGE = 12;

    private const MIN_PER_PAGE = 1;

    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly CampaignRepositoryContract $campaigns,
        private readonly PersistenceAdapterContract $persistence,
    ) {
    }

    public function listDisplayable(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): CampaignPagedResultDTO
    {
        [$page, $perPage] = self::clampPaging($page, $perPage);
        $offset = ($page - 1) * $perPage;
        $result = $this->campaigns->listDisplayable($perPage, $offset);

        return new CampaignPagedResultDTO(
            items: $result['items'],
            total: $result['total'],
            page: $page,
            perPage: $perPage,
            hasMore: ($page * $perPage) < $result['total'],
        );
    }

    public function listFeatured(int $limit = 6): array
    {
        if ($limit < 1) {
            $limit = 1;
        }

        return $this->campaigns->listFeatured($limit);
    }

    public function findById(string $id): ?CampaignDetailDTO
    {
        return $this->campaigns->findById($id);
    }

    public function findBySlug(string $slug): ?CampaignDetailDTO
    {
        return $this->campaigns->findBySlug($slug);
    }

    /**
     * Progress rollup aggregated over donations in successful states
     * (`payment_verified`, `receipt_generated`, `completed`).
     *
     * Returns one CampaignProgressDTO per `currency_code` present in
     * the donations table for this campaign. Multi-currency safety:
     * amounts NEVER mix currencies; consumers receive one row each.
     *
     * Doctrine: hits the `donations_verified_recent_idx` partial index
     * on the Postgres path. SQLite has its own equivalent indexes from
     * the mirror migration.
     *
     * @return list<CampaignProgressDTO>
     */
    public function progressFor(string $campaignId): array
    {
        $result = $this->persistence->query(
            'SELECT  currency_code,
                    SUM(amount_minor) AS raised_amount_minor,
                    COUNT(*)          AS donation_count,
                    COUNT(DISTINCT donor_id) AS distinct_donor_count
            FROM    donations
            WHERE   campaign_id = :cid
              AND   deleted_at IS NULL
              AND   state IN (\'payment_verified\',\'receipt_generated\',\'completed\')
            GROUP BY currency_code
            ORDER BY currency_code ASC',
            ['cid' => $campaignId],
        );
        if ($result->isFailure()) {
            throw new RuntimeException(
                'CampaignsQueryService::progressFor failed: '.($result->error() ?? 'unknown')
            );
        }

        return array_map(
            static fn (array $row): CampaignProgressDTO => new CampaignProgressDTO(
                campaignId: $campaignId,
                currencyCode: (string) $row['currency_code'],
                raisedAmountMinor: (int) ($row['raised_amount_minor'] ?? 0),
                donationCount: (int) ($row['donation_count'] ?? 0),
                distinctDonorCount: (int) ($row['distinct_donor_count'] ?? 0),
            ),
            $result->value(),
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function clampPaging(int $page, int $perPage): array
    {
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < self::MIN_PER_PAGE) {
            $perPage = self::DEFAULT_PER_PAGE;
        } elseif ($perPage > self::MAX_PER_PAGE) {
            $perPage = self::MAX_PER_PAGE;
        }

        return [$page, $perPage];
    }
}
