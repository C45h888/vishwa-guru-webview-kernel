<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Campaigns;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Services\PublicMediaPresentationService;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Seo\Contracts\SeoMetaContract;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detail page for one campaign by slug.
 *
 * Replaces the 501 stub reserved at routes/campaigns.php:30-35.
 * Looks up via CampaignsQueryContract::findBySlug (null on miss / soft-deleted /
 * non-displayable), then loads the per-currency progress rollup, related
 * campaigns, and recent activity. Returns 404 when the contract returns null.
 */
final class ShowController
{
    private const RECENT_WINDOW_DAYS = 7;
    private const RELATED_LIMIT = 3;

    public function __invoke(
        CampaignsQueryContract $campaigns,
        PublicMediaPresentationService $media,
        DonationRepositoryContract $donations,
        SeoMetaContract $seo,
        string $slug,
    ): Response {
        $detail = $campaigns->findBySlug($slug);
        if ($detail === null) {
            throw new NotFoundHttpException("Campaign [{$slug}] not found");
        }

        $progress = array_map(
            static fn ($dto) => $dto->toArray(),
            $campaigns->progressFor($detail->id),
        );

        $relatedCampaigns = $this->resolveRelatedCampaigns(
            $campaigns,
            $media,
            $detail,
        );

        $recentDonorCount = $this->resolveRecentDonorCount(
            $donations,
            $detail->id,
        );

        $enriched = $media->enrich(
            $detail->toArray(),
            'cover_image_file_id',
            'cover_image',
        );

        $cover = is_array($enriched['cover_image'] ?? null) ? $enriched['cover_image'] : [];

        return Inertia::render('campaigns/Show', [
            'campaign' => $enriched,
            'progress' => $progress,
            'relatedCampaigns' => $relatedCampaigns,
            'recentDonorCount' => $recentDonorCount,
            'recentWindowDays' => self::RECENT_WINDOW_DAYS,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
            'seo' => $seo->forPage(
                title: (string) ($enriched['title'] ?? ''),
                description: $enriched['short_description'] ?? null,
                imageUrl: $cover['url'] ?? null,
                imageAlt: $cover['alt_text'] ?? null,
                type: 'article',
            ),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function resolveRelatedCampaigns(
        CampaignsQueryContract $campaigns,
        PublicMediaPresentationService $media,
        $detail,
    ): array {
        // Try same category first; fall back to any active campaigns.
        $byCategory = $campaigns->listDisplayable(1, 50);
        $sameCat = [];
        $anyActive = [];
        foreach ($byCategory->items as $dto) {
            if ($dto->id === $detail->id) {
                continue;
            }
            $arr = $dto->toArray();
            $arr['cover_image'] = null;
            if ($dto->category === $detail->category) {
                $sameCat[] = $arr;
            } else {
                $anyActive[] = $arr;
            }
            if (count($sameCat) >= self::RELATED_LIMIT) {
                break;
            }
        }
        $picks = array_slice($sameCat, 0, self::RELATED_LIMIT);
        if (count($picks) < self::RELATED_LIMIT) {
            $needed = self::RELATED_LIMIT - count($picks);
            $picks = array_merge($picks, array_slice($anyActive, 0, $needed));
        }
        return $media->enrichMany($picks, 'cover_image_file_id', 'cover_image');
    }

    private function resolveRecentDonorCount(
        DonationRepositoryContract $donations,
        string $campaignId,
    ): ?int {
        // The Donation entity exposes `createdAt` only as a private readonly
        // property, and the repository contract has no "count since X" method
        // — adding either is out of scope for this UI pass. The Show page
        // treats null as "hide the recent-activity callout" and renders a
        // graceful empty state when present. Returns null until the data
        // layer is extended (Phase 2 candidate).
        return null;
    }
}
