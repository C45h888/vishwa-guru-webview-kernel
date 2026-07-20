<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns\Domain\DTOs;

use App\Campaigns\Domain\DTOs\CampaignDetailDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CampaignDetailDTOTest extends TestCase
{
    private function makeDto(string $state, bool $isActive): CampaignDetailDTO
    {
        return new CampaignDetailDTO(
            id: 'campaign_x',
            slug: 's',
            title: 't',
            shortDescription: 'short',
            description: 'long',
            category: 'general',
            currencyCode: 'INR',
            targetAmountMinor: 100_000,
            isFeatured: false,
            isActive: $isActive,
            state: $state,
            displayOrder: 0,
            startsAt: null,
            endsAt: null,
            coverImageFileId: null,
            metadata: ['foo' => 'bar'],
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }

    public function testIsActiveReflectsState(): void
    {
        $this->assertTrue($this->makeDto('active', true)->isActive);
        $this->assertFalse($this->makeDto('completed', false)->isActive);
        $this->assertFalse($this->makeDto('archived', false)->isActive);
        $this->assertFalse($this->makeDto('draft', false)->isActive);
    }

    public function testDescriptionNullable(): void
    {
        $dto = new CampaignDetailDTO(
            id: 'campaign_x',
            slug: 's',
            title: 't',
            shortDescription: null,
            description: null,
            category: 'general',
            currencyCode: 'INR',
            targetAmountMinor: null,
            isFeatured: false,
            isActive: true,
            state: 'active',
            displayOrder: 0,
            startsAt: null,
            endsAt: null,
            coverImageFileId: null,
            metadata: [],
            createdAt: new DateTimeImmutable(),
        );
        $this->assertNull($dto->description);
    }

    public function testMetadataDefaultsToEmptyArray(): void
    {
        $dto = $this->makeDto('active', true);
        $this->assertSame(['foo' => 'bar'], $dto->metadata);
    }

    public function testToArrayShape(): void
    {
        $dto = $this->makeDto('active', true);
        $arr = $dto->toArray();
        $this->assertArrayHasKey('is_active', $arr);
        $this->assertArrayHasKey('display_order', $arr);
        $this->assertArrayHasKey('metadata', $arr);
        $this->assertArrayHasKey('created_at', $arr);
        $this->assertTrue($arr['is_active']);
        $this->assertSame(['foo' => 'bar'], $arr['metadata']);
    }
}
