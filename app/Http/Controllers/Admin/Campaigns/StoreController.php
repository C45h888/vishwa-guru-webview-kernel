<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Campaigns;

use App\Campaigns\Contracts\CampaignAuthoringContract;
use App\Campaigns\Domain\Exceptions\DuplicateCampaignSlugException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaigns\StoreCampaignRequest;
use Illuminate\Http\RedirectResponse;

/**
 * StoreController — handles POST /admin/campaigns.
 *
 * Doctrine: thin controller. FormRequest validates input and produces
 * the CampaignDraftInput DTO; the service does the actual insert; the
 * controller translates outcomes to HTTP responses:
 *
 *   - success           → redirect to the edit page for the new campaign
 *   - validation failure → redirect back to the form with errors
 *   - duplicate slug    → redirect back to the form with a slug error
 *   - other failure     → redirect back to the form with a generic error
 *
 * The redirect-after-POST pattern avoids the classic "double submit on
 * refresh" pitfall; Inertia automatically preserves flash data so the
 * Svelte page reads it back through its standard props.
 */
final class StoreController extends Controller
{
    public function __construct(
        private readonly CampaignAuthoringContract $authoring,
    ) {
    }

    public function __invoke(StoreCampaignRequest $request): RedirectResponse
    {
        try {
            $campaign = $this->authoring->create($request->toInput());
        } catch (DuplicateCampaignSlugException $e) {
            return redirect()
                ->route('admin.campaigns.create')
                ->withErrors(['slug' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.campaigns.edit', ['campaign' => $campaign->id])
            ->with('status', 'Campaign created.');
    }
}
