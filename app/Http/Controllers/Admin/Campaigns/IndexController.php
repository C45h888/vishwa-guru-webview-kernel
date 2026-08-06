<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Campaigns;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IndexController — admin campaigns list page.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - Lists ALL campaigns including drafts, ordered by updated_at DESC
 *     so freshly-edited rows surface first. The public surface still
 *     filters to {active, completed}; the admin sees the full set.
 *   - Thin controller — pagination comes from the URL (?page=) and
 *     the paged DTO from the repository; no business logic.
 */
final class IndexController extends Controller
{
    public function __construct(
        private readonly CampaignRepositoryContract $repository,
    ) {
    }

    public function __invoke(): Response
    {
        $page = (int) request()->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $result = $this->repository->listAllIncludingDrafts($perPage, $offset);
        $items = array_map(
            static fn ($dto) => $dto->toArray(),
            $result['items'],
        );

        return Inertia::render('admin/campaigns/Index', [
            'campaigns' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'has_more' => ($page * $perPage) < $result['total'],
            ],
        ]);
    }
}
