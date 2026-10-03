<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Campaigns;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Services\PublicMediaPresentationService;
use App\Seo\Contracts\SeoMetaContract;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse page for all displayable campaigns.
 *
 * Replaces the 501 stub reserved at routes/campaigns.php:23-28.
 * Page is server-rendered via Inertia; data is fetched once per request
 * via the read-side contract and serialised through CampaignSummaryDTO::toArray().
 *
 * Filter + sort state lives in the URL (?category=, ?sort=, ?page=) so the
 * page is shareable, back-button-friendly, and works with SSR.
 */
final class IndexController
{
    private const ALLOWED_SORTS = ['featured', 'newest', 'ending'];

    public function __invoke(
        Request $request,
        CampaignsQueryContract $campaigns,
        PublicMediaPresentationService $media,
        SeoMetaContract $seo,
    ): Response {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 12;

        $currentCategory = $this->normaliseCategory(
            $request->query('category'),
        );
        $currentSort = $this->normaliseSort($request->query('sort'));

        $paged = $campaigns->listDisplayable($page, $perPage);

        // Enrich with cover images via the public-media service.
        $enriched = $media->enrichMany(
            array_map(
                static fn ($dto) => $dto->toArray(),
                $paged->items,
            ),
            'cover_image_file_id',
            'cover_image',
        );

        // Per-campaign raised amount, donor count, days-remaining.
        // N+1 for now — the catalog is small; if this grows we can
        // batch progressFor() into a single query.
        $enriched = $this->enrichWithProgress(
            $campaigns,
            $enriched,
            $currentSort,
        );

        $categories = $this->resolveCategories($campaigns);

        return Inertia::render('campaigns/Index', [
            'campaigns' => $enriched,
            'pillarMedia' => $this->resolvePillarMedia($media),
            'pagination' => [
                'page' => $paged->page,
                'per_page' => $paged->perPage,
                'total' => $paged->total,
                'has_more' => $paged->hasMore,
            ],
            'categories' => $categories,
            'totalCampaigns' => $paged->total,
            'currentCategory' => $currentCategory,
            'currentSort' => $currentSort,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
            'seo' => $seo->forPage(
                title: 'Campaigns',
                description: 'Browse donation campaigns — the pooled fund for daily school support and the staged campus programme.',
            ),
        ]);
    }

    /**
     * Hydrate the three static editorial pillar images on the campaigns
     * page from their canonical public-media assets. Ids are declared in
     * `config/campaigns.php` and seeded by `align_campaign_images.php`.
     * Order is preserved; a missing/unpublished asset yields null and the
     * Svelte layer falls back to the canonical storage path.
     *
     * @return list<array<string, mixed>|null>
     */
    private function resolvePillarMedia(PublicMediaPresentationService $media): array
    {
        $config = config('campaigns.pillars', []);
        if (! is_array($config) || $config === []) {
            return [];
        }

        $items = $media->enrichMany(
            array_map(
                static fn (array $entry): array => ['cms_media_id' => $entry['cms_media_id'] ?? null],
                $config,
            ),
            'cms_media_id',
            'media',
        );

        return array_values(array_map(
            static fn (array $entry) => $entry['media'] ?? null,
            $items,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function enrichWithProgress(
        CampaignsQueryContract $campaigns,
        array $items,
        string $sort,
    ): array {
        $now = Carbon::now()->getTimestamp();
        $out = [];
        foreach ($items as $item) {
            $id = is_string($item['id'] ?? null) ? $item['id'] : null;
            $raised = null;
            $donorCount = null;
            if ($id !== null) {
                $progress = $campaigns->progressFor($id);
                $raised = 0;
                foreach ($progress as $row) {
                    $raised += (int) ($row->raisedAmountMinor ?? 0);
                    $donorCount = ($donorCount ?? 0)
                        + (int) ($row->distinctDonorCount ?? 0);
                }
            }

            $daysRemaining = null;
            if (is_string($item['ends_at'] ?? null)) {
                $ts = Carbon::parse($item['ends_at'])->getTimestamp();
                $daysRemaining = (int) ceil(($ts - $now) / 86400);
            }

            // For sort=newest we don't need to do anything client-side;
            // the Svelte layer relies on the server's ordering.
            $item['raised_amount_minor'] = $raised;
            $item['donor_count'] = $donorCount;
            $item['days_remaining'] = $daysRemaining;
            $out[] = $item;
        }
        return $out;
    }

    /**
     * @return list<string>
     */
    private function resolveCategories(CampaignsQueryContract $campaigns): array
    {
        // We don't have a listCategories() method on the contract; pull
        // a generous page of displayable items and de-dupe their category
        // fields. The catalog is small enough that this is fine.
        $paged = $campaigns->listDisplayable(1, 100);
        $seen = [];
        foreach ($paged->items as $dto) {
            $cat = $dto->category;
            if (is_string($cat) && $cat !== '' && ! isset($seen[$cat])) {
                $seen[$cat] = true;
            }
        }
        return array_keys($seen);
    }

    private function normaliseCategory(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }
        $v = trim($raw);
        if ($v === '') {
            return null;
        }
        // Only allow safe slug-like values.
        if (! preg_match('/^[a-z0-9_-]{1,32}$/', $v)) {
            return null;
        }
        return $v;
    }

    private function normaliseSort(mixed $raw): string
    {
        if (! is_string($raw)) {
            return 'featured';
        }
        return in_array($raw, self::ALLOWED_SORTS, true) ? $raw : 'featured';
    }
}
