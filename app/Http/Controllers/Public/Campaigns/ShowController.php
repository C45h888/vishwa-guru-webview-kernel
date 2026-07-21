<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Campaigns;

use App\Campaigns\Contracts\CampaignsQueryContract;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Detail page for one campaign by slug.
 *
 * Replaces the 501 stub reserved at routes/campaigns.php:30-35.
 * Looks up via CampaignsQueryContract::findBySlug (null on miss / soft-deleted /
 * non-displayable), then loads the per-currency progress rollup. Returns 404
 * when the contract returns null.
 */
final class ShowController
{
    public function __invoke(CampaignsQueryContract $campaigns, string $slug): Response
    {
        $detail = $campaigns->findBySlug($slug);
        if ($detail === null) {
            throw new NotFoundHttpException("Campaign [{$slug}] not found");
        }

        $progress = array_map(
            static fn ($dto) => $dto->toArray(),
            $campaigns->progressFor($detail->id),
        );

        return Inertia::render('campaigns/Show', [
            'campaign' => $detail->toArray(),
            'progress' => $progress,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
