<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Campaigns;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * EditController — admin "edit campaign" form (preloaded with row data).
 *
 * Doctrine: thin controller. Fetches the full row (including drafts)
 * via the repository, hands it to the Svelte page. The form's submit
 * button posts to UpdateController; this controller only renders.
 */
final class EditController extends Controller
{
    /**
     * @var list<string>
     */
    private const ALLOWED_STATES = ['draft', 'active', 'completed'];

    public function __construct(
        private readonly CampaignRepositoryContract $repository,
    ) {
    }

    public function __invoke(string $campaign): Response
    {
        $dto = $this->repository->findByIdIncludingDrafts($campaign);

        if ($dto === null) {
            abort(404, 'Campaign not found.');
        }

        return Inertia::render('admin/campaigns/Edit', [
            'campaign' => $dto->toArray(),
            'states' => self::ALLOWED_STATES,
        ]);
    }
}
