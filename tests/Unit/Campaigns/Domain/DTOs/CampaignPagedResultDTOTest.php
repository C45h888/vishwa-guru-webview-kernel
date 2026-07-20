<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns\Domain\DTOs;

use App\Campaigns\Domain\DTOs\CampaignPagedResultDTO;
use App\Campaigns\Domain\DTOs\CampaignSummaryDTO;
use PHPUnit\Framework\TestCase;

final class CampaignPagedResultDTOTest extends TestCase
{
    public function testEmptyItemsList(): void
    {
        $dto = new CampaignPagedResultDTO(
            items: [],
            total: 0,
            page: 1,
            perPage: 12,
            hasMore: false,
        );
        $this->assertSame([], $dto->items);
        $this->assertSame(0, $dto->total);
        $this->assertFalse($dto->hasMore);
    }

    public function testHasMoreTrueWhenMorePages(): void
    {
        $dto = new CampaignPagedResultDTO(
            items: [],
            total: 25,
            page: 1,
            perPage: 12,
            hasMore: true,
        );
        $this->assertTrue($dto->hasMore);
    }

    public function testHasMoreFalseOnLastPage(): void
    {
        $dto = new CampaignPagedResultDTO(
            items: [],
            total: 24,
            page: 2,
            perPage: 12,
            hasMore: false,
        );
        $this->assertFalse($dto->hasMore);
    }

    public function testNegativeTotalRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CampaignPagedResultDTO(
            items: [],
            total: -1,
            page: 1,
            perPage: 12,
            hasMore: false,
        );
    }

    public function testPageBelowOneRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CampaignPagedResultDTO(
            items: [],
            total: 0,
            page: 0,
            perPage: 12,
            hasMore: false,
        );
    }

    public function testToArrayShape(): void
    {
        $summary = new CampaignSummaryDTO(
            id: 'campaign_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            category: 'c',
            currencyCode: 'INR',
            targetAmountMinor: null,
            isFeatured: false,
            state: 'active',
            startsAt: null,
            endsAt: null,
            coverImageFileId: null,
        );
        $dto = new CampaignPagedResultDTO(
            items: [$summary],
            total: 1,
            page: 1,
            perPage: 12,
            hasMore: false,
        );
        $arr = $dto->toArray();
        $this->assertSame(['items', 'total', 'page', 'per_page', 'has_more'], array_keys($arr));
        $this->assertCount(1, $arr['items']);
        $this->assertSame('campaign_x', $arr['items'][0]['id']);
        $this->assertSame(12, $arr['per_page']);
    }
}
