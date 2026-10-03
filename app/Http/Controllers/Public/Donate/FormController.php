<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public\Donate;

use App\Campaigns\Contracts\CampaignsQueryContract;
use App\Seo\Contracts\SeoMetaContract;
use App\Shared\Policies\LegalPolicyVersions;
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
 *
 * Deep linking (Phase 3 routing):
 *   ?campaign={slug}     preselect campaign (passed to Svelte as preselectSlug)
 *   ?amount={rupees}     preselect amount in rupees (preselectAmountRupees)
 *   ?recurring={key}     preselect recurring flag (preselectRecurring, V1 UI-only)
 *   ?anonymous=1         preselect anonymous donation (preselectAnonymous)
 */
final class FormController
{
    public function __invoke(CampaignsQueryContract $campaigns, SeoMetaContract $seo): Response
    {
        $paged = $campaigns->listDisplayable(1, 50);

        // Every active displayable campaign is offered as a giving
        // destination. The donor chooses which campaign their gift
        // supports; the form submits that campaign's typed ID to the
        // Razorpay checkout contract. Filtering to a single pooled fund
        // here would defeat the multi-campaign surface and left donors
        // unable to give to any other active campaign.
        $campaignsList = array_map(
            static fn ($dto) => $dto->toArray(),
            array_values(array_filter(
                $paged->items,
                static fn ($dto) => $dto->state === 'active',
            )),
        );

        // Default currency comes from the first campaign or 'INR'.
        $defaultCurrency = $campaignsList[0]['currency_code'] ?? 'INR';

        // Deep-link query params (all optional, all read as strings).
        $preselectSlug = request()->query('campaign');
        $preselectAmount = request()->query('amount');
        $preselectRecurring = request()->query('recurring');
        $preselectAnonymous = request()->query('anonymous') === '1';

        // 80G certificate threshold (minor units). Donations above this
        // require a PAN to issue an 80G certificate, so the form reveals
        // the PAN/address fields from here up. Sourced from config so the
        // UI threshold cannot drift from the substrate verify80G() rule.
        $eightyGThresholdMinor = (int) config(
            'receipts.80g.certificate_threshold_minor',
            500_00,
        );

        return Inertia::render('payments/Donate', [
            'campaigns' => $campaignsList,
            'defaultCurrency' => $defaultCurrency,
            'preselectSlug' => $preselectSlug,
            'preselectAmountRupees' => $preselectAmount,
            'preselectRecurring' => $preselectRecurring,
            'preselectAnonymous' => $preselectAnonymous,
            'eightyGThresholdMinor' => $eightyGThresholdMinor,
            'termsPolicyVersion' => LegalPolicyVersions::TERMS,
            'privacyPolicyVersion' => LegalPolicyVersions::PRIVACY,
            'appName' => (string) config('app.name', 'Temple Trust'),
            'appUrl' => (string) config('app.url'),
            'seo' => $seo->forPage(
                title: 'Donate',
                description: 'Support daily annadanam and schooling for children in need of care — including blind and deaf pupils — and the proposed healing and service campus near Nanjangud.',
            ),
        ]);
    }
}
