<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Events\Domain\Repositories\EventRepositoryContract;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DashboardController — post-login landing for the canonical admin.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - Thin controller. Reads come from the Campaigns + Events
 *     authoring-side repositories (so drafts are visible).
 *   - `campaigns_count` surfaces the full row count via the
 *     COUNT(*) OVER () window on listAllIncludingDrafts.
 *   - `events_count` mirrors the same pattern on the events side.
 *   - `upcoming_events` is the read-side EventsQueryContract's
 *     listUpcoming (displayable future events, ascending) limited
 *     to a small dashboard tile set.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly CampaignRepositoryContract $campaigns,
        private readonly EventRepositoryContract $events,
        private readonly \App\Events\Contracts\EventsQueryContract $eventsQuery,
    ) {
    }

    public function __invoke(): Response
    {
        $campaignsPaged = $this->campaigns->listAllIncludingDrafts(perPage: 1, offset: 0);
        $featuredCampaigns = $this->campaigns->listFeatured(limit: 3);

        $eventsPaged = $this->events->listAllIncludingDrafts(perPage: 1, offset: 0);
        $upcomingEvents = $this->eventsQuery->listUpcoming(limit: 5);

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'campaigns_count' => $campaignsPaged['total'],
                'events_count' => $eventsPaged['total'],
                // EventSummaryDTO::toArray() output — the Svelte page
                // renders the same shape it already understands from
                // the public events surface.
                'upcoming_events' => array_map(
                    static fn ($dto) => $dto->toArray(),
                    $upcomingEvents,
                ),
            ],
            'featured_campaigns' => array_map(
                static fn ($dto) => $dto->toArray(),
                $featuredCampaigns,
            ),
        ]);
    }
}
