<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns\Domain\DTOs;

use App\Campaigns\Domain\DTOs\CampaignSummaryDTO;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CampaignSummaryDTOTest extends TestCase
{
    private function valid(): CampaignSummaryDTO
    {
        return new CampaignSummaryDTO(
            id: 'campaign_01ARZ3NDEKTSV4RRFFQ69G5FAV',
            slug: 'annual-fund-2026',
            title: 'Annual Fund 2026',
            shortDescription: 'Help us reach this year\'s goal.',
            category: 'general',
            currencyCode: 'INR',
            targetAmountMinor: 5_000_000,
            isFeatured: true,
            state: 'active',
            startsAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            endsAt: new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
            coverImageFileId: 'file_asset_01HABC',
        );
    }

    public function testConstructionWithValidArgs(): void
    {
        $dto = $this->valid();
        $this->assertSame('campaign_01ARZ3NDEKTSV4RRFFQ69G5FAV', $dto->id);
        $this->assertSame('annual-fund-2026', $dto->slug);
        $this->assertSame('Annual Fund 2026', $dto->title);
        $this->assertSame('INR', $dto->currencyCode);
        $this->assertSame(5_000_000, $dto->targetAmountMinor);
        $this->assertTrue($dto->isFeatured);
        $this->assertSame('active', $dto->state);
    }

    public function testEmptyIdRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CampaignSummaryDTO(
            id: '',
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
    }

    public function testEmptySlugRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CampaignSummaryDTO(
            id: 'campaign_x',
            slug: '',
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
    }

    public function testInvalidCurrencyCodeLengthRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CampaignSummaryDTO(
            id: 'campaign_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            category: 'c',
            currencyCode: 'DOLLAR',
            targetAmountMinor: null,
            isFeatured: false,
            state: 'active',
            startsAt: null,
            endsAt: null,
            coverImageFileId: null,
        );
    }

    public function testNonPositiveTargetAmountRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CampaignSummaryDTO(
            id: 'campaign_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            category: 'c',
            currencyCode: 'INR',
            targetAmountMinor: 0,
            isFeatured: false,
            state: 'active',
            startsAt: null,
            endsAt: null,
            coverImageFileId: null,
        );
    }

    public function testToArrayShapeSnakeCase(): void
    {
        $dto = $this->valid();
        $arr = $dto->toArray();
        $this->assertSame(
            [
                'id', 'slug', 'title', 'short_description', 'category',
                'currency_code', 'target_amount_minor', 'is_featured',
                'state', 'starts_at', 'ends_at', 'cover_image_file_id',
            ],
            array_keys($arr),
        );
        $this->assertSame('campaign_01ARZ3NDEKTSV4RRFFQ69G5FAV', $arr['id']);
        $this->assertSame('INR', $arr['currency_code']);
        $this->assertSame(5_000_000, $arr['target_amount_minor']);
        $this->assertTrue($arr['is_featured']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $arr['starts_at']);
    }

    public function testNullTimestampsAppearAsNullInToArray(): void
    {
        $dto = new CampaignSummaryDTO(
            id: 'campaign_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            category: 'c',
            currencyCode: 'USD',
            targetAmountMinor: null,
            isFeatured: false,
            state: 'draft',
            startsAt: null,
            endsAt: null,
            coverImageFileId: null,
        );
        $arr = $dto->toArray();
        $this->assertNull($arr['starts_at']);
        $this->assertNull($arr['ends_at']);
        $this->assertNull($arr['cover_image_file_id']);
    }
}
