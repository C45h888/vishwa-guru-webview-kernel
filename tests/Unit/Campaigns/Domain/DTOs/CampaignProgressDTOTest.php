<?php

declare(strict_types=1);

namespace Tests\Unit\Campaigns\Domain\DTOs;

use App\Campaigns\Domain\DTOs\CampaignProgressDTO;
use PHPUnit\Framework\TestCase;

final class CampaignProgressDTOTest extends TestCase
{
    public function testConstructionValidatesPositiveAmount(): void
    {
        $dto = new CampaignProgressDTO(
            campaignId: 'campaign_x',
            currencyCode: 'INR',
            raisedAmountMinor: 250_000,
            donationCount: 5,
            distinctDonorCount: 4,
        );
        $this->assertSame('campaign_x', $dto->campaignId);
        $this->assertSame('INR', $dto->currencyCode);
        $this->assertSame(250_000, $dto->raisedAmountMinor);
        $this->assertSame(5, $dto->donationCount);
        $this->assertSame(4, $dto->distinctDonorCount);
    }

    public function testConstructionAllowsZeroAmount(): void
    {
        // Brand-new campaign with no donations yet — zero is valid.
        $dto = new CampaignProgressDTO(
            campaignId: 'campaign_x',
            currencyCode: 'USD',
            raisedAmountMinor: 0,
            donationCount: 0,
            distinctDonorCount: 0,
        );
        $this->assertSame(0, $dto->raisedAmountMinor);
    }

    public function testNegativeAmountRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CampaignProgressDTO(
            campaignId: 'campaign_x',
            currencyCode: 'INR',
            raisedAmountMinor: -1,
            donationCount: 0,
            distinctDonorCount: 0,
        );
    }

    public function testInvalidCurrencyCodeRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CampaignProgressDTO(
            campaignId: 'campaign_x',
            currencyCode: 'US',
            raisedAmountMinor: 0,
            donationCount: 0,
            distinctDonorCount: 0,
        );
    }

    public function testToArrayShape(): void
    {
        $dto = new CampaignProgressDTO(
            campaignId: 'campaign_x',
            currencyCode: 'INR',
            raisedAmountMinor: 100,
            donationCount: 2,
            distinctDonorCount: 2,
        );
        $arr = $dto->toArray();
        $this->assertSame(
            ['campaign_id', 'currency_code', 'raised_amount_minor', 'donation_count', 'distinct_donor_count'],
            array_keys($arr),
        );
        $this->assertSame(100, $arr['raised_amount_minor']);
        $this->assertSame(2, $arr['donation_count']);
    }
}
