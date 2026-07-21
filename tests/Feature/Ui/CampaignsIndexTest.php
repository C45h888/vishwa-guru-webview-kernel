<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /campaigns (campaigns.Index).
 *
 * Exercises the full pipeline: HTTP request → web middleware →
 * Inertia middleware → Campaigns\IndexController → campaigns.Index
 * component render with paged listDisplayable data.
 */
final class CampaignsIndexTest extends InfrastructureTestCase
{
    public function testIndexRendersPagedCampaignsList(): void
    {
        $this->seedCampaign(id: 'cmp_ui_01', state: 'active', title: 'Temple Renovation');
        $this->seedCampaign(id: 'cmp_ui_02', state: 'active', title: 'Annadhanam Drive');
        $this->seedCampaign(id: 'cmp_ui_03', state: 'completed', title: 'Past Yagna');

        $response = $this->get('/campaigns');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('campaigns/Index')
            ->has('campaigns', 3)
            ->has('pagination', fn (AssertableInertia $p) => $p
                ->where('page', 1)
                ->where('per_page', 12)
                ->where('total', 3)
                ->where('has_more', false)
                ->etc()
            )
            ->etc()
        );
    }

    public function testIndexRendersEmptyWhenNoDisplayableCampaigns(): void
    {
        $response = $this->get('/campaigns');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('campaigns/Index')
            ->has('campaigns', 0)
            ->etc()
        );
    }

    private function seedCampaign(
        string $id,
        string $state,
        string $title,
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
                'slug' => strtolower($id),
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
}
