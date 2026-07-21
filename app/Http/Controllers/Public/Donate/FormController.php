<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Donate;

use App\Campaigns\Contracts\CampaignsQueryContract;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Donation form page (GET /donate).
 *
 * Returns a list of currently-displayable campaigns so the donor can
 * pick one. The form submission itself is NOT handled here — the
 * Svelte Donate.svelte form POSTs to /api/v1/razorpay/checkout via
 * fetch() and then navigates to /donate/success?gateway_order_id=…
 * handled by SubmitController.
 */
final class FormController
{
    public function __invoke(CampaignsQueryContract $campaigns): Response
    {
        $paged = $campaigns->listDisplayable(1, 50);

        $campaignsList = array_map(
            static fn ($dto) => $dto->toArray(),
            $paged->items,
        );

        // Default currency comes from the first campaign or 'INR'.
        $defaultCurrency = $campaignsList[0]['currency_code'] ?? 'INR';

        // Pre-selected campaign when ?campaign=slug is set.
        $preselectSlug = request()->query('campaign');

        return Inertia::render('payments/Donate', [
            'campaigns' => $campaignsList,
            'defaultCurrency' => $defaultCurrency,
            'preselectSlug' => $preselectSlug,
            'appName' => config('app.name', 'Temple Trust'),
            'appUrl' => config('app.url'),
        ]);
    }
}
