<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Campaigns;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Cms\Services\PublicMediaPresentationService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Browse page for all displayable campaigns.
 *
 * Replaces the 501 stub reserved at routes/campaigns.php:23-28.
 * Page is server-rendered via Inertia; data is fetched once per request
 * via the read-side contract and serialised through CampaignSummaryDTO::toArray().
 */
final class IndexController
{
    public function __invoke(CampaignsQueryContract $campaigns, PublicMediaPresentationService $media): Response
    {
        $page = max(1, (int) request()->query('page', 1));
        $perPage = 12;

        $paged = $campaigns->listDisplayable($page, $perPage);

        return Inertia::render('campaigns/Index', [
            'campaigns' => $media->enrichMany(array_map(
                static fn ($dto) => $dto->toArray(),
                $paged->items,
            ), 'cover_image_file_id', 'cover_image'),
            'pagination' => [
                'page' => $paged->page,
                'per_page' => $paged->perPage,
                'total' => $paged->total,
                'has_more' => $paged->hasMore,
            ],
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
