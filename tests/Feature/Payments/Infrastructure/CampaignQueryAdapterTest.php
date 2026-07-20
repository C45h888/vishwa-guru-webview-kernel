<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Infrastructure\Adapters\CampaignQueryAdapter;
use DateTimeImmutable;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Integration tests for the Shape-A bridge adapter
 * App\Payments\Infrastructure\Adapters\CampaignQueryAdapter.
 *
 * The bridge is the only sanctioned cross-kernel read surface from CMS
 * to Payments — it must never throw, must filter out soft-deleted and
 * non-displayable rows, and must degrade to null/false otherwise.
 *
 * Persists fixtures directly via $this->adapter->execute() (the same
 * path every concrete Payments repo uses) so this test depends only
 * on the contracts layer, not on the existence of a campaign repo
 * wrapper.
 */
final class CampaignQueryAdapterTest extends InfrastructureTestCase
{
    private const CAMPAIGN_ID = 'campaign_01HCTESTACT';

    protected function setUp(): void
    {
        parent::setUp();

        // Seed one row in each terminal state we care about so we can
        // probe displayability across the matrix.
        $this->seedCampaign(id: 'campaign_01HACTIVE',    state: 'active',    title: 'Active');
        $this->seedCampaign(id: 'campaign_01HCOMPLETED', state: 'completed', title: 'Completed');
        $this->seedCampaign(id: 'campaign_01HDRAFT',     state: 'draft',     title: 'Draft');
        $this->seedCampaign(id: 'campaign_01HARCHIVED',  state: 'archived',  title: 'Archived');
        $this->seedCampaign(
            id: self::CAMPAIGN_ID,
            state: 'active',
            title: 'Test Campaign',
            deletedAt: '2026-07-20T00:00:00+00:00',
        );
    }

    public function testFindActiveByIdReturnsDtoForActiveCampaign(): void
    {
        $adapter = new CampaignQueryAdapter($this->adapter);

        // Insert non-deleted row.
        $this->seedCampaign(
            id: 'campaign_01HLIVE',
            state: 'active',
            title: 'Live Campaign',
            targetMinor: 100000,
            currency: 'INR',
        );

        $dto = $adapter->findActiveById('campaign_01HLIVE');

        $this->assertNotNull($dto, 'findActiveById returned null for live campaign');
        $this->assertSame('campaign_01HLIVE', $dto->id());
        $this->assertSame('Live Campaign', $dto->title());
        $this->assertSame('live-campaign', $dto->slug());
        $this->assertSame('INR', $dto->currencyCode());
        $this->assertSame(100000, $dto->targetAmountMinor());
        $this->assertTrue($dto->isActive());
    }

    public function testFindActiveByIdReturnsNullForSoftDeletedCampaign(): void
    {
        $adapter = new CampaignQueryAdapter($this->adapter);

        $dto = $adapter->findActiveById(self::CAMPAIGN_ID);

        $this->assertNull($dto, 'findActiveById must return null for soft-deleted rows');
    }

    public function testIsDisplayableRespectsState(): void
    {
        $adapter = new CampaignQueryAdapter($this->adapter);

        $this->assertTrue($adapter->isDisplayable('campaign_01HACTIVE'), 'active must be displayable');
        $this->assertTrue($adapter->isDisplayable('campaign_01HCOMPLETED'), 'completed must be displayable');
        $this->assertFalse($adapter->isDisplayable('campaign_01HDRAFT'), 'draft must NOT be displayable');
        $this->assertFalse($adapter->isDisplayable('campaign_01HARCHIVED'), 'archived must NOT be displayable');
        $this->assertFalse($adapter->isDisplayable(self::CAMPAIGN_ID), 'soft-deleted must NOT be displayable');
        $this->assertFalse($adapter->isDisplayable('campaign_01HNONEXISTENT'), 'missing must NOT be displayable');
        $this->assertFalse($adapter->isDisplayable(''), 'empty id must NOT be displayable');
    }

    /**
     * Seed a single `campaigns` row directly via the adapter.
     * Avoids the CampaignRepository wrapper, which is out of scope
     * for kernel-readiness.
     */
    private function seedCampaign(
        string $id,
        string $state,
        string $title,
        ?int $targetMinor = null,
        string $currency = 'INR',
        ?string $deletedAt = null,
        ?string $slug = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $result = $this->adapter->execute(
            'INSERT INTO campaigns (
                id, slug, title, category, currency_code, state,
                target_amount_minor, display_order, is_featured,
                metadata, created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :cat, :ccy, :state,
                :target, 0, false,
                \'{}\', :created, :updated, :deleted
            )',
            [
                'id' => $id,
                'slug' => $slug ?? self::slugify($title),
                'title' => $title,
                'cat' => 'general',
                'ccy' => $currency,
                'state' => $state,
                'target' => $targetMinor,
                'created' => $now,
                'updated' => $now,
                'deleted' => $deletedAt,
            ],
        );
        $this->assertFalse(
            $result->isFailure(),
            'seedCampaign insert failed: '.($result->error() ?? 'unknown')
        );
    }

    private static function slugify(string $title): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? '');
    }
}
