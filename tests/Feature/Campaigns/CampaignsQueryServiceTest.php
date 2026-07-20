<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns;

use App\Campaigns\Domain\Repositories\CampaignRepositoryContract;
use App\Campaigns\Infrastructure\Repositories\EloquentCampaignRepository;
use App\Campaigns\Services\CampaignsQueryService;
use DateTimeImmutable;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Integration tests for CampaignsQueryService.
 *
 * Service-level invariants tested here:
 *   - perPage is clamped to [1, 50]
 *   - page < 1 is bumped to 1
 *   - progressFor() aggregates by currency_code (one row each)
 *   - progressFor() excludes failed / pending / soft-deleted donations
 */
final class CampaignsQueryServiceTest extends InfrastructureTestCase
{
    private CampaignsQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CampaignsQueryService(
            new EloquentCampaignRepository($this->adapter),
            $this->adapter,
        );
    }

    public function testListDisplayableEnforcesPerPageClampUpper(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->seedCampaign(id: 'campaign_01HIDX'.$i, state: 'active', title: 'T'.$i);
        }

        // Request perPage=200, should clamp to 50
        $page = $this->service->listDisplayable(1, 200);
        $this->assertSame(50, $page->perPage);
        $this->assertCount(50, $page->items);
        $this->assertSame(60, $page->total);
        $this->assertTrue($page->hasMore);
    }

    public function testListDisplayableBumpsPageBelowOne(): void
    {
        $this->seedCampaign(id: 'campaign_01HX', state: 'active', title: 'X');
        $page = $this->service->listDisplayable(0, 12);
        $this->assertSame(1, $page->page);
    }

    public function testProgressForAggregatesByCurrency(): void
    {
        $this->seedCampaign(id: 'campaign_01HMULTI', state: 'active', title: 'Multi');
        $this->seedDonation(
            id: 'don_01HINR1',
            campaignId: 'campaign_01HMULTI',
            amountMinor: 100_000,
            currency: 'INR',
            state: 'payment_verified',
        );
        $this->seedDonation(
            id: 'don_01HINR2',
            campaignId: 'campaign_01HMULTI',
            amountMinor: 50_000,
            currency: 'INR',
            state: 'completed',
        );
        $this->seedDonation(
            id: 'don_01HUSD1',
            campaignId: 'campaign_01HMULTI',
            amountMinor: 25_000,
            currency: 'USD',
            state: 'receipt_generated',
        );

        $progress = $this->service->progressFor('campaign_01HMULTI');
        $this->assertCount(2, $progress); // one row per currency

        $byCurrency = [];
        foreach ($progress as $row) {
            $byCurrency[$row->currencyCode] = $row;
        }

        $this->assertSame(150_000, $byCurrency['INR']->raisedAmountMinor);
        $this->assertSame(2, $byCurrency['INR']->donationCount);

        $this->assertSame(25_000, $byCurrency['USD']->raisedAmountMinor);
        $this->assertSame(1, $byCurrency['USD']->donationCount);
    }

    public function testProgressForExcludesFailedAndPendingDonations(): void
    {
        $this->seedCampaign(id: 'campaign_01HFX', state: 'active', title: 'FX');
        $this->seedDonation(id: 'don_01HFAIL', campaignId: 'campaign_01HFX', amountMinor: 99_999, currency: 'INR', state: 'failed');
        $this->seedDonation(id: 'don_01HDRAFT', campaignId: 'campaign_01HFX', amountMinor: 99_999, currency: 'INR', state: 'draft');
        $this->seedDonation(id: 'don_01HCANCEL', campaignId: 'campaign_01HFX', amountMinor: 99_999, currency: 'INR', state: 'cancelled');
        $this->seedDonation(id: 'don_01HGOOD', campaignId: 'campaign_01HFX', amountMinor: 1_000, currency: 'INR', state: 'completed');

        $progress = $this->service->progressFor('campaign_01HFX');
        $this->assertCount(1, $progress);
        $this->assertSame(1_000, $progress[0]->raisedAmountMinor);
        $this->assertSame(1, $progress[0]->donationCount);
    }

    public function testProgressForExcludesSoftDeletedDonations(): void
    {
        $this->seedCampaign(id: 'campaign_01HSD', state: 'active', title: 'SD');
        $this->seedDonation(
            id: 'don_01HSOFTDEL',
            campaignId: 'campaign_01HSD',
            amountMinor: 5_000,
            currency: 'INR',
            state: 'completed',
            deletedAt: '2026-07-01T00:00:00+00:00',
        );

        $progress = $this->service->progressFor('campaign_01HSD');
        $this->assertCount(0, $progress);
    }

    public function testProgressForEmptyReturnsEmptyList(): void
    {
        $this->seedCampaign(id: 'campaign_01HEMPTY', state: 'active', title: 'Empty');
        $this->assertSame([], $this->service->progressFor('campaign_01HEMPTY'));
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
