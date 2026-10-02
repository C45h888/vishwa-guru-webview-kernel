<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

/**
 * @covers ReceiptRepository
 * @covers Receipt
 */
final class ReceiptRepositoryTest extends InfrastructureTestCase
{
    private \App\Payments\Infrastructure\Repositories\ReceiptRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new \App\Payments\Infrastructure\Repositories\ReceiptRepository($this->adapter);
    }

    public function testSaveFindByIdRoundTrip(): void
    {
        $donationId = EntityId::generate('donation');
        $paymentId = EntityId::generate('payment');
        $campaignId = EntityId::generate('campaign');

        $receipt = Receipt::issue(
            donationId: $donationId,
            paymentId: $paymentId,
            campaignId: $campaignId,
            receiptNumber: 'TR-2026-000001-AAAA1111',
            campaignTitleSnapshot: 'Test Campaign',
            donorName: 'Test Donor',
            amountMinor: 50000,
            currency: Currency::INR,
            contentHash: hash('sha256', 'receipt-content-test'),
            donorEmail: 'donor@test.com',
        );

        $this->repo->save($receipt);

        $found = $this->repo->findById($receipt->id());
        $this->assertNotNull($found);
        $this->assertSame($receipt->id()->value(), $found->id()->value());
        $this->assertSame('TR-2026-000001-AAAA1111', $found->receiptNumber());
        $this->assertSame('Test Donor', $found->donorName());
        $this->assertSame(50000, $found->amountMinor());
        $this->assertSame(Currency::INR->value, $found->currency()->value);
    }

    public function testFindByReceiptNumber(): void
    {
        $donationId = EntityId::generate('donation');
        $paymentId = EntityId::generate('payment');
        $campaignId = EntityId::generate('campaign');

        $receipt = Receipt::issue(
            donationId: $donationId,
            paymentId: $paymentId,
            campaignId: $campaignId,
            receiptNumber: 'TR-2026-000002-BBBB2222',
            campaignTitleSnapshot: 'Campaign B',
            donorName: 'Donor B',
            amountMinor: 75000,
            currency: Currency::INR,
            contentHash: hash('sha256', 'receipt-b-content'),
        );

        $this->repo->save($receipt);

        $found = $this->repo->findByReceiptNumber('TR-2026-000002-BBBB2222');
        $this->assertNotNull($found);
        $this->assertSame($receipt->id()->value(), $found->id()->value());
    }

    public function testFindByReceiptNumberReturnsNullWhenNotFound(): void
    {
        $found = $this->repo->findByReceiptNumber('TR-2099-ZZZZ');
        $this->assertNull($found);
    }

    public function testFindByDonationId(): void
    {
        $donationId = EntityId::generate('donation');
        $paymentId = EntityId::generate('payment');
        $campaignId = EntityId::generate('campaign');

        $receipt = Receipt::issue(
            donationId: $donationId,
            paymentId: $paymentId,
            campaignId: $campaignId,
            receiptNumber: 'TR-2026-000003-CCCC3333',
            campaignTitleSnapshot: 'Campaign C',
            donorName: 'Donor C',
            amountMinor: 30000,
            currency: Currency::USD,
            contentHash: hash('sha256', 'receipt-c-content'),
        );

        $this->repo->save($receipt);

        $found = $this->repo->findByDonationId($donationId);
        $this->assertNotNull($found);
        $this->assertSame($donationId->value(), $found->donationId()->value());
    }

    public function testFindByDonationIdReturnsNullWhenNotFound(): void
    {
        $fakeDonationId = EntityId::generate('donation');
        $found = $this->repo->findByDonationId($fakeDonationId);
        $this->assertNull($found);
    }

    public function testFindByTransactionId(): void
    {
        $donationId = EntityId::generate('donation');
        $paymentId = EntityId::generate('payment');
        $campaignId = EntityId::generate('campaign');

        $receipt = Receipt::issue(
            donationId: $donationId,
            paymentId: $paymentId,
            campaignId: $campaignId,
            receiptNumber: 'TR-2026-000004-DDDD4444',
            campaignTitleSnapshot: 'Campaign D',
            donorName: 'Donor D',
            amountMinor: 20000,
            currency: Currency::INR,
            contentHash: hash('sha256', 'receipt-d-content'),
        );

        $this->repo->save($receipt);

        $found = $this->repo->findByTransactionId($paymentId);
        $this->assertNotNull($found);
        $this->assertSame($paymentId->value(), $found->paymentId()->value());
    }

    public function testFindByTransactionIdReturnsNullWhenNotFound(): void
    {
        $fakePaymentId = EntityId::generate('payment');
        $found = $this->repo->findByTransactionId($fakePaymentId);
        $this->assertNull($found);
    }

    public function testExistsForTransactionTrue(): void
    {
        $donationId = EntityId::generate('donation');
        $paymentId = EntityId::generate('payment');
        $campaignId = EntityId::generate('campaign');

        $receipt = Receipt::issue(
            donationId: $donationId,
            paymentId: $paymentId,
            campaignId: $campaignId,
            receiptNumber: 'TR-2026-000005-EEEE5555',
            campaignTitleSnapshot: 'Campaign E',
            donorName: 'Donor E',
            amountMinor: 40000,
            currency: Currency::INR,
            contentHash: hash('sha256', 'receipt-e-content'),
        );

        $this->repo->save($receipt);

        $this->assertTrue($this->repo->existsForTransaction($paymentId));
    }

    public function testExistsForTransactionFalse(): void
    {
        $fakePaymentId = EntityId::generate('payment');
        $this->assertFalse($this->repo->existsForTransaction($fakePaymentId));
    }
}
