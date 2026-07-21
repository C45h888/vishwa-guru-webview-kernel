<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /campaigns/{slug} (campaigns.Show).
 *
 * Asserts the detail page renders, progress is included in props,
 * and missing slugs return 404 via Symfony's NotFoundHttpException.
 */
final class CampaignsShowTest extends InfrastructureTestCase
{
    public function testShowRendersCampaignDetailWithProgress(): void
    {
        $this->seedCampaign(id: 'cmp_show_1', state: 'active', title: 'Test Campaign', slug: 'test-campaign');
        $this->seedDonation(id: 'don_show_1', campaignId: 'cmp_show_1', amountMinor: 50_000, currency: 'INR', state: 'completed');

        $response = $this->get('/campaigns/test-campaign');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('campaigns/Show')
            ->where('campaign.slug', 'test-campaign')
            ->where('campaign.title', 'Test Campaign')
            ->has('progress')
            ->etc()
        );
    }

    public function testShowReturns404ForMissingSlug(): void
    {
        $response = $this->get('/campaigns/does-not-exist');

        $response->assertNotFound();
    }

    private function seedCampaign(
        string $id,
        string $state,
        string $title,
        ?string $slug = null,
        ?string $deletedAt = null,
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
                :created, :updated, :deleted
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
                'deleted' => $deletedAt,
            ],
        );
    }

    private function seedDonation(
        string $id,
        string $campaignId,
        int $amountMinor,
        string $currency,
        string $state,
        ?string $deletedAt = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO donations (
                id, campaign_id, amount_minor, currency_code, state,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :cid, :amt, :ccy, :state,
                \'{}\', :created, :updated, :deleted
            )',
            [
                'id' => $id,
                'cid' => $campaignId,
                'amt' => $amountMinor,
                'ccy' => $currency,
                'state' => $state,
                'created' => $now,
                'updated' => $now,
                'deleted' => $deletedAt,
            ],
        );
    }
}
