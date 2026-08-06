<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Campaigns;

use App\Campaigns\Contracts\CampaignAuthoringContract;
use App\Campaigns\Domain\Exceptions\DuplicateCampaignSlugException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaigns\UpdateCampaignRequest;
use Illuminate\Http\RedirectResponse;

/**
 * UpdateController — handles PUT /admin/campaigns/{campaign}.
 *
 * Doctrine mirrors StoreController — see that file's docblock for the
 * full rationale. The only difference: 404 when the row doesn't exist
 * (matches RepositoryContract::update returning null).
 */
final class UpdateController extends Controller
{
    public function __construct(
        private readonly CampaignAuthoringContract $authoring,
    ) {
    }

    public function __invoke(UpdateCampaignRequest $request, string $campaign): RedirectResponse
    {
        try {
            $updated = $this->authoring->update($campaign, $request->toInput());
        } catch (DuplicateCampaignSlugException $e) {
            return redirect()
                ->route('admin.campaigns.edit', ['campaign' => $campaign])
                ->withErrors(['slug' => $e->getMessage()])
                ->withInput();
        }

        if ($updated === null) {
            abort(404, 'Campaign not found.');
        }

        return redirect()
            ->route('admin.campaigns.edit', ['campaign' => $updated->id])
            ->with('status', 'Campaign updated.');
    }
}
