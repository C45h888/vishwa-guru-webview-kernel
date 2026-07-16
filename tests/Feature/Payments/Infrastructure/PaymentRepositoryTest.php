<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Infrastructure;

use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Infrastructure\Repositories\PaymentRepository;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

/**
 * @covers PaymentRepository
 * @covers Payment
 */
final class PaymentRepositoryTest extends InfrastructureTestCase
{
    private PaymentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PaymentRepository($this->adapter);
    }

    public function testSaveFindByIdRoundTrip(): void
    {
        $donationId = EntityId::generate('donation');
        $payment = Payment::initialize(
            donationId: $donationId,
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 50000,
            currency: Currency::INR,
            idempotencyKey: 'pay_idem_001',
        );

        $this->repo->save($payment);

        $found = $this->repo->findById($payment->id());
        $this->assertNotNull($found);
        $this->assertSame($payment->id()->value(), $found->id()->value());
        $this->assertSame($payment->amountMinor(), $found->amountMinor());
        $this->assertSame($payment->currency()->value, $found->currency()->value);
        $this->assertSame(TransactionStatus::INITIALIZED->value, $found->status()->value);
    }

    public function testFindByGatewayOrderId(): void
    {
        $donationId = EntityId::generate('donation');
        $payment = Payment::initialize(
            donationId: $donationId,
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 100000,
            currency: Currency::INR,
            idempotencyKey: 'pay_idem_002',
        );

        $this->repo->save($payment);

        // Simulate a provider order ID being set via update
        $updated = $payment->withChanges(['provider_order_id' => 'razor_order_abc123']);
        $this->repo->update($updated);

        $found = $this->repo->findByGatewayOrderId('razor_order_abc123');
        $this->assertNotNull($found);
        $this->assertSame($payment->id()->value(), $found->id()->value());
    }

    public function testFindByGatewayOrderIdReturnsNullWhenNotFound(): void
    {
        $found = $this->repo->findByGatewayOrderId('nonexistent_order');
        $this->assertNull($found);
    }

    public function testFindByDonationId(): void
    {
        $donationId = EntityId::generate('donation');
        $payment = Payment::initialize(
            donationId: $donationId,
            providerCode: PaymentProvider::PAYPAL,
            amountMinor: 25000,
            currency: Currency::USD,
            idempotencyKey: 'pay_idem_003',
        );

        $this->repo->save($payment);

        $found = $this->repo->findByDonationId($donationId);
        $this->assertNotNull($found);
        $this->assertSame($donationId->value(), $found->donationId()->value());
    }

    public function testExistsForGatewayOrderTrue(): void
    {
        $donationId = EntityId::generate('donation');
        $payment = Payment::initialize(
            donationId: $donationId,
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 75000,
            currency: Currency::INR,
            idempotencyKey: 'pay_idem_004',
        );

        $this->repo->save($payment);
        $updated = $payment->withChanges(['provider_order_id' => 'razor_order_exists']);
        $this->repo->update($updated);

        $this->assertTrue($this->repo->existsForGatewayOrder('razor_order_exists'));
    }

    public function testExistsForGatewayOrderFalse(): void
    {
        $this->assertFalse($this->repo->existsForGatewayOrder('razor_order_notfound'));
    }

    public function testCountByStatus(): void
    {
        $donationId1 = EntityId::generate('donation');
        $donationId2 = EntityId::generate('donation');

        $p1 = Payment::initialize(
            donationId: $donationId1,
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 50000,
            currency: Currency::INR,
            idempotencyKey: 'pay_idem_005a',
        );
        $p2 = Payment::initialize(
            donationId: $donationId2,
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 50000,
            currency: Currency::INR,
            idempotencyKey: 'pay_idem_005b',
        );

        $this->repo->save($p1);
        $this->repo->save($p2);

        $count = $this->repo->countByStatus(TransactionStatus::INITIALIZED);
        $this->assertSame(2, $count);

        $none = $this->repo->countByStatus(TransactionStatus::CAPTURED);
        $this->assertSame(0, $none);
    }

    public function testUpdateStatus(): void
    {
        $donationId = EntityId::generate('donation');
        $payment = Payment::initialize(
            donationId: $donationId,
            providerCode: PaymentProvider::PAYPAL,
            amountMinor: 30000,
            currency: Currency::USD,
            idempotencyKey: 'pay_idem_006',
        );

        $this->repo->save($payment);

        $updated = $this->repo->updateStatus($payment->id(), TransactionStatus::PENDING);
        $this->assertSame(TransactionStatus::PENDING->value, $updated->status()->value);
    }

    public function testUpdateStatusThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $fakeId = EntityId::generate('payment');
        $this->repo->updateStatus($fakeId, TransactionStatus::PENDING);
    }

    public function testLockByIdForUpdate(): void
    {
        $donationId = EntityId::generate('donation');
        $payment = Payment::initialize(
            donationId: $donationId,
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 90000,
            currency: Currency::INR,
            idempotencyKey: 'pay_idem_007',
        );

        $this->repo->save($payment);

        $locked = $this->repo->lockByIdForUpdate($payment->id());
        $this->assertNotNull($locked);
        $this->assertSame($payment->id()->value(), $locked->id()->value());
    }

    public function testLockByIdForUpdateReturnsNullWhenNotFound(): void
    {
        $fakeId = EntityId::generate('payment');
        $locked = $this->repo->lockByIdForUpdate($fakeId);
        $this->assertNull($locked);
    }
}
