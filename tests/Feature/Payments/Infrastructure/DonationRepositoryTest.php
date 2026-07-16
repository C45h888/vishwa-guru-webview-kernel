<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Infrastructure\Repositories\DonationRepository;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

/**
 * @covers DonationRepository
 * @covers Donation
 */
final class DonationRepositoryTest extends InfrastructureTestCase
{
    private DonationRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DonationRepository($this->adapter);
    }

    public function testSaveFindByIdRoundTrip(): void
    {
        $campaignId = EntityId::generate('campaign');
        $donor = DonorIdentity::identified('Test Donor', 'donor@test.com', '+919876543210');
        $donation = Donation::draft(
            campaignId: $campaignId,
            donor: $donor,
            amountMinor: 50000,
            currency: Currency::INR,
            idempotencyKey: 'don_idem_001',
        );

        $this->repo->save($donation);

        $found = $this->repo->findById($donation->id());
        $this->assertNotNull($found);
        $this->assertSame($donation->id()->value(), $found->id()->value());
        $this->assertSame($donation->amountMinor(), $found->amountMinor());
        $this->assertSame(DonationState::DRAFT->value, $found->state()->value);
        $this->assertSame('Test Donor', $found->donorNameSnapshot());
    }

    public function testFindByCampaignIdPagination(): void
    {
        $campaignId = EntityId::generate('campaign');

        for ($i = 0; $i < 5; $i++) {
            $donor = DonorIdentity::identified("Donor {$i}", "donor{$i}@test.com");
            $d = Donation::draft(
                campaignId: $campaignId,
                donor: $donor,
                amountMinor: 10000 + $i,
                currency: Currency::INR,
                idempotencyKey: "don_idem_pag_{$i}",
            );
            $this->repo->save($d);
        }

        // Different campaign
        $otherCampaign = EntityId::generate('campaign');
        $otherDonor = DonorIdentity::identified('Other Donor', 'other@test.com');
        $otherDonation = Donation::draft(
            campaignId: $otherCampaign,
            donor: $otherDonor,
            amountMinor: 99999,
            currency: Currency::INR,
            idempotencyKey: 'don_idem_pag_other',
        );
        $this->repo->save($otherDonation);

        $page1 = $this->repo->findByCampaignId($campaignId, 2, 0);
        $this->assertCount(2, $page1);

        $page2 = $this->repo->findByCampaignId($campaignId, 2, 2);
        $this->assertCount(2, $page2);

        $page3 = $this->repo->findByCampaignId($campaignId, 2, 4);
        $this->assertCount(1, $page3);

        $allForCampaign = $this->repo->findByCampaignId($campaignId, 100, 0);
        $this->assertCount(5, $allForCampaign);
    }

    public function testFindByDonorId(): void
    {
        $campaignId = EntityId::generate('campaign');
        $donor = DonorIdentity::identified('Named Donor', 'named@test.com');
        $donation = Donation::draft(
            campaignId: $campaignId,
            donor: $donor,
            amountMinor: 25000,
            currency: Currency::INR,
            idempotencyKey: 'don_idem_003',
        );

        $this->repo->save($donation);

        // DonorId is set when donor is known
        $found = $this->repo->findByDonorId($donation->donorId(), 100, 0);
        $this->assertNotEmpty($found);
        $this->assertSame($donation->id()->value(), $found[0]->id()->value());
    }

    public function testExistsForIdempotencyKeyTrue(): void
    {
        $campaignId = EntityId::generate('campaign');
        $donor = DonorIdentity::identified('Idem Test', 'idem@test.com');
        $donation = Donation::draft(
            campaignId: $campaignId,
            donor: $donor,
            amountMinor: 15000,
            currency: Currency::INR,
            idempotencyKey: 'don_idem_exists_yes',
        );

        $this->repo->save($donation);

        $this->assertTrue($this->repo->existsForIdempotencyKey('don_idem_exists_yes'));
    }

    public function testExistsForIdempotencyKeyFalse(): void
    {
        $this->assertFalse($this->repo->existsForIdempotencyKey('don_idem_nonexistent'));
    }

    public function testLockByIdForUpdate(): void
    {
        $campaignId = EntityId::generate('campaign');
        $donor = DonorIdentity::identified('Lock Test', 'lock@test.com');
        $donation = Donation::draft(
            campaignId: $campaignId,
            donor: $donor,
            amountMinor: 45000,
            currency: Currency::INR,
            idempotencyKey: 'don_idem_lock',
        );

        $this->repo->save($donation);

        $locked = $this->repo->lockByIdForUpdate($donation->id());
        $this->assertNotNull($locked);
        $this->assertSame($donation->id()->value(), $locked->id()->value());
    }

    public function testLockByIdForUpdateReturnsNullWhenNotFound(): void
    {
        $fakeId = EntityId::generate('donation');
        $locked = $this->repo->lockByIdForUpdate($fakeId);
        $this->assertNull($locked);
    }

    public function testSaveAndFindByIdempotencyKey(): void
    {
        $campaignId = EntityId::generate('campaign');
        $donor = DonorIdentity::identified('Idem Key Test', 'idemkey@test.com');
        $donation = Donation::draft(
            campaignId: $campaignId,
            donor: $donor,
            amountMinor: 60000,
            currency: Currency::INR,
            idempotencyKey: 'don_idem_unique_01',
        );

        $this->repo->save($donation);

        $found = $this->repo->findByIdempotencyKey('don_idem_unique_01');
        $this->assertNotNull($found);
        $this->assertSame($donation->id()->value(), $found->id()->value());
    }
}
