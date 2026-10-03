<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\ReceiptDeliveryState;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
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

    // ------------------------------------------------------------------
    // 2026-10-03 wave: durable delivery state
    // ------------------------------------------------------------------

    public function testUpdatePersistsDeliveredStatus(): void
    {
        $receipt = $this->freshReceipt('TR-2026-000010-FFFF1010', 'delivered@persist.test');
        $this->repo->save($receipt);

        $updated = $receipt->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_DISPATCHED,
        );
        $this->repo->update($updated);

        $found = $this->repo->findById($receipt->id());
        $this->assertNotNull($found);
        $this->assertSame(ReceiptDeliveryState::DELIVERED->value, $found->deliveryStatus());
        $this->assertTrue($found->isDelivered());
        $this->assertNotNull($found->deliveredAt());
    }

    public function testUpdatePersistsFailedStatusAndChannel(): void
    {
        $receipt = $this->freshReceipt('TR-2026-000011-GGGG1111', 'failed@persist.test');
        $this->repo->save($receipt);

        $updated = $receipt->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_FAILED,
            ['channel' => 'email', 'address' => 'failed@persist.test'],
        );
        $this->repo->update($updated);

        $found = $this->repo->findById($receipt->id());
        $this->assertNotNull($found);
        $this->assertSame(ReceiptDeliveryState::FAILED->value, $found->deliveryStatus());
        $this->assertSame('email', $found->deliveryChannel());
        $this->assertSame('failed@persist.test', $found->deliveryAddress());
    }

    public function testRedispatchPersistsPendingStatusInDb(): void
    {
        $receipt = $this->freshReceipt('TR-2026-000012-HHHH1212', 'redispatch@persist.test');
        $this->repo->save($receipt);

        $failed = $receipt->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_FAILED,
            ['channel' => 'email', 'address' => 'redispatch@persist.test'],
        );
        $this->repo->update($failed);

        // Bookkeeping redispatch: markDelivered(PENDING) → pending, no
        // delivered_at (a failed row never had one).
        $redispatched = $failed->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_REDISPATCHED,
            ['redispatched_at' => (new DateTimeImmutable())->format(DATE_ATOM)],
        );
        $this->repo->update($redispatched);

        $found = $this->repo->findById($receipt->id());
        $this->assertNotNull($found);
        $this->assertSame(ReceiptDeliveryState::PENDING->value, $found->deliveryStatus());
        $this->assertNull($found->deliveredAt());
        $this->assertSame('email', $found->deliveryChannel());
    }

    public function testFindUncompletedDeliveriesSelection(): void
    {
        $failed = $this->freshReceipt('TR-2026-000013-IIII1313', 'failed@backfill.test');
        $this->repo->save($failed);
        $this->repo->update($failed->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_FAILED,
            ['channel' => 'email', 'address' => 'failed@backfill.test'],
        ));

        $delivered = $this->freshReceipt('TR-2026-000014-JJJJ1414', 'delivered@backfill.test');
        $this->repo->save($delivered);
        $this->repo->update($delivered->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_DISPATCHED,
        ));

        $freshPending = $this->freshReceipt('TR-2026-000015-KKKK1515', 'fresh@backfill.test');
        $this->repo->save($freshPending);

        $anonymous = $this->freshReceipt('TR-2026-000016-LLLL1616', null);
        $this->repo->save($anonymous);

        // Only the failed receipt is a backfill candidate: the delivered
        // row is complete, the fresh pending row is inside the grace
        // window, and the anonymous row has no donor email.
        $candidates = $this->repo->findUncompletedDeliveries(3600);
        $this->assertCount(1, $candidates);
        $this->assertSame($failed->id()->value(), $candidates[0]->id()->value());

        // Backdate the fresh pending row's updated_at beyond the grace
        // window — it now qualifies as stale pending.
        $staleTs = (new DateTimeImmutable('-2 hours'))->format(DATE_ATOM);
        $this->adapter->execute(
            'UPDATE receipts SET updated_at = :ts WHERE id = :id',
            ['ts' => $staleTs, 'id' => $freshPending->id()->value()],
        );

        $candidates = $this->repo->findUncompletedDeliveries(3600);
        $ids = array_map(
            static fn (Receipt $r): string => $r->id()->value(),
            $candidates,
        );
        $this->assertContains($freshPending->id()->value(), $ids);
        $this->assertContains($failed->id()->value(), $ids);
        $this->assertNotContains($delivered->id()->value(), $ids);
        $this->assertNotContains($anonymous->id()->value(), $ids);
    }

    private function freshReceipt(
        string $receiptNumber,
        ?string $donorEmail,
    ): Receipt {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: EntityId::generate('payment'),
            campaignId: EntityId::generate('campaign'),
            receiptNumber: $receiptNumber,
            campaignTitleSnapshot: 'Delivery State Campaign',
            donorName: 'Backfill Donor',
            amountMinor: 12000,
            currency: Currency::INR,
            contentHash: hash('sha256', $receiptNumber),
            donorEmail: $donorEmail,
        );
    }
}
