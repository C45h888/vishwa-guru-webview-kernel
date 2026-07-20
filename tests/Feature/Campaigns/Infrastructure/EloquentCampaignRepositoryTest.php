<?php

declare(strict_types=1);

namespace Tests\Feature\Campaigns\Infrastructure;

use App\Campaigns\Infrastructure\Repositories\EloquentCampaignRepository;
use DateTimeImmutable;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Integration tests for EloquentCampaignRepository.
 *
 * V1 is read-only. The repository must:
 *   - return only displayable rows (state IN active|completed)
 *   - exclude soft-deleted rows (deleted_at IS NULL)
 *   - honor limit + offset for listDisplayable
 *   - order featured first then by display_order ASC
 */
final class EloquentCampaignRepositoryTest extends InfrastructureTestCase
{
    private EloquentCampaignRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentCampaignRepository($this->adapter);
    }

    public function testFindByIdReturnsDetailForDisplayableCampaign(): void
    {
        $this->seedCampaign(id: 'campaign_01HACTIVE', state: 'active', title: 'Active');
        $detail = $this->repo->findById('campaign_01HACTIVE');

        $this->assertNotNull($detail);
        $this->assertSame('campaign_01HACTIVE', $detail->id);
        $this->assertTrue($detail->isActive);
        $this->assertSame('active', $detail->state);
    }

    public function testFindByIdReturnsNullForDraft(): void
    {
        $this->seedCampaign(id: 'campaign_01HDRAFT', state: 'draft', title: 'Draft');
        $this->assertNull($this->repo->findById('campaign_01HDRAFT'));
    }

    public function testFindByIdReturnsNullForSoftDeleted(): void
    {
        $this->seedCampaign(
            id: 'campaign_01HSOFTDEL',
            state: 'active',
            title: 'Soft Deleted',
            deletedAt: '2026-07-01T00:00:00+00:00',
        );
        $this->assertNull($this->repo->findById('campaign_01HSOFTDEL'));
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById('campaign_01HNONEXISTENT'));
    }

    public function testFindBySlugFindsDisplayable(): void
    {
        $this->seedCampaign(id: 'campaign_01HCOMPLETED', state: 'completed', title: 'Completed', slug: 'completed-slug');
        $detail = $this->repo->findBySlug('completed-slug');

        $this->assertNotNull($detail);
        $this->assertSame('completed-slug', $detail->slug);
        $this->assertFalse($detail->isActive); // completed is not active
    }

    public function testListDisplayableExcludesNonDisplayableAndSoftDeleted(): void
    {
        $this->seedCampaign(id: 'campaign_01HACTIVE', state: 'active', title: 'A');
        $this->seedCampaign(id: 'campaign_01HCOMPLETED', state: 'completed', title: 'B');
        $this->seedCampaign(id: 'campaign_01HDRAFT', state: 'draft', title: 'C');
        $this->seedCampaign(id: 'campaign_01HARCHIVED', state: 'archived', title: 'D');
        $this->seedCampaign(
            id: 'campaign_01HSOFTDEL',
            state: 'active',
            title: 'E',
            deletedAt: '2026-07-01T00:00:00+00:00',
        );

        $result = $this->repo->listDisplayable(12, 0);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['items']);
    }

    public function testListDisplayableRespectsPagingAndTotal(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedCampaign(id: 'campaign_01HIDX'.$i, state: 'active', title: 'T'.$i);
        }

        $page1 = $this->repo->listDisplayable(2, 0);
        $this->assertCount(2, $page1['items']);
        $this->assertSame(5, $page1['total']);

        $page3 = $this->repo->listDisplayable(2, 4);
        $this->assertCount(1, $page3['items']);
        $this->assertSame(5, $page3['total']);
    }

    public function testListDisplayableOrdersFeaturedFirstThenDisplayOrder(): void
    {
        $this->seedCampaign(id: 'campaign_01HA', state: 'active', title: 'A', displayOrder: 0, isFeatured: false);
        $this->seedCampaign(id: 'campaign_01HB', state: 'active', title: 'B', displayOrder: 10, isFeatured: true);
        $this->seedCampaign(id: 'campaign_01HC', state: 'active', title: 'C', displayOrder: 5, isFeatured: true);

        $result = $this->repo->listDisplayable(12, 0);
        $this->assertCount(3, $result['items']);
        // ORDER BY is_featured DESC, display_order ASC:
        //   featured: C (5), B (10) — ascending within featured
        //   then non-featured: A
        $this->assertSame('campaign_01HC', $result['items'][0]->id); // featured, order 5
        $this->assertSame('campaign_01HB', $result['items'][1]->id); // featured, order 10
        $this->assertSame('campaign_01HA', $result['items'][2]->id); // not featured
    }

    public function testListFeaturedRespectsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedCampaign(
                id: 'campaign_01HF'.$i,
                state: 'active',
                title: 'F'.$i,
                displayOrder: $i,
                isFeatured: true,
            );
        }

        $featured = $this->repo->listFeatured(2);
        $this->assertCount(2, $featured);
        $this->assertSame('campaign_01HF0', $featured[0]->id);
        $this->assertSame('campaign_01HF1', $featured[1]->id);
    }

    public function testListFeaturedExcludesNonDisplayable(): void
    {
        $this->seedCampaign(id: 'campaign_01HFDRAFT', state: 'draft', title: 'Draft', isFeatured: true);
        $this->seedCampaign(id: 'campaign_01HFACTIVE', state: 'active', title: 'Active', isFeatured: true);

        $featured = $this->repo->listFeatured(6);
        $this->assertCount(1, $featured);
        $this->assertSame('campaign_01HFACTIVE', $featured[0]->id);
    }

    private function seedCampaign(
        string $id,
        string $state,
        string $title,
        int $displayOrder = 0,
        bool $isFeatured = false,
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
                :target, :dorder, :featured,
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
                'dorder' => $displayOrder,
                'featured' => $isFeatured ? 1 : 0,
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
