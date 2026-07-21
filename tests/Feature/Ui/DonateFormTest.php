<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /donate (payments.Donate form page).
 *
 * Seeds 2 active campaigns and asserts the Inertia payload contains
 * the campaigns list + defaults. No Donation row should be inserted
 * by this GET (the form lives client-side; the submit POST lives at
 * /api/v1/razorpay/checkout which is covered by DonateSubmitTest).
 */
final class DonateFormTest extends InfrastructureTestCase
{
    public function testFormRendersWithCampaignsList(): void
    {
        $this->seedCampaign(id: 'cmp_donate_1', state: 'active', title: 'Temple Fund');
        $this->seedCampaign(id: 'cmp_donate_2', state: 'active', title: 'Annadhanam');

        $response = $this->get('/donate');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('payments/Donate')
            ->has('campaigns', 2)
            ->has('defaultCurrency')
            ->etc()
        );
    }

    public function testFormAcceptsPreselectCampaignQuery(): void
    {
        $this->seedCampaign(id: 'cmp_donate_x', state: 'active', title: 'Pre-selected Campaign', slug: 'pre-selected');

        $response = $this->get('/donate?campaign=pre-selected');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('payments/Donate')
            ->where('preselectSlug', 'pre-selected')
            ->etc()
        );
    }

    public function testFormRendersEmptyWhenNoActiveCampaigns(): void
    {
        $response = $this->get('/donate');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('payments/Donate')
            ->has('campaigns', 0)
            ->etc()
        );
    }

    private function seedCampaign(
        string $id,
        string $state,
        string $title,
        ?string $slug = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO campaigns (
                id, slug, title, category, currency_code, state,
                display_order, is_featured, metadata,
                created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :cat, :ccy, :state,
                0, 0, \'{}\',
                :created, :updated, NULL
            )',
            [
                'id' => $id,
                'slug' => $slug ?? strtolower($id),
                'title' => $title,
                'cat' => 'general',
                'ccy' => 'INR',
                'state' => $state,
                'created' => $now,
                'updated' => $now,
            ],
        );
    }
}
