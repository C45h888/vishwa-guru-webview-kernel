<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Services;

use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Events\PaymentValidated;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Payments\Jobs\GenerateReceiptJob;
use App\Payments\Services\ReceiptIssuanceCoordinator;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The coordinator is the single binding between a validated payment and
 * receipt generation. These tests lock its contract: dispatch the job for
 * a successful payment, skip duplicates, and skip non-successful payments.
 */
final class ReceiptIssuanceCoordinatorTest extends TestCase
{
    public function test_it_dispatches_a_receipt_job_for_a_successful_payment(): void
    {
        Queue::fake();

        $payment = $this->paymentInStatus(TransactionStatus::CAPTURED);

        $payments = $this->createMock(PaymentRepositoryContract::class);
        $payments->method('findById')->willReturn($payment);

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('existsForTransaction')->willReturn(false);

        $audit = $this->createMock(AuditEventRepositoryContract::class);
        $audit->expects($this->once())->method('append');

        $this->coordinator($payments, $receipts, $audit)->handle($this->eventFor($payment));

        Queue::assertPushed(GenerateReceiptJob::class);
    }

    public function test_it_skips_when_a_receipt_already_exists(): void
    {
        Queue::fake();

        $payment = $this->paymentInStatus(TransactionStatus::CAPTURED);

        $payments = $this->createMock(PaymentRepositoryContract::class);
        $payments->method('findById')->willReturn($payment);

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('existsForTransaction')->willReturn(true);

        $audit = $this->createMock(AuditEventRepositoryContract::class);
        $audit->expects($this->never())->method('append');

        $this->coordinator($payments, $receipts, $audit)->handle($this->eventFor($payment));

        Queue::assertNotPushed(GenerateReceiptJob::class);
    }

    public function test_it_skips_non_successful_payments(): void
    {
        Queue::fake();

        $payment = $this->paymentInStatus(TransactionStatus::INITIALIZED);

        $payments = $this->createMock(PaymentRepositoryContract::class);
        $payments->method('findById')->willReturn($payment);

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->expects($this->never())->method('existsForTransaction');

        $audit = $this->createMock(AuditEventRepositoryContract::class);
        $audit->expects($this->never())->method('append');

        $this->coordinator($payments, $receipts, $audit)->handle($this->eventFor($payment));

        Queue::assertNotPushed(GenerateReceiptJob::class);
    }

    public function test_it_skips_when_payment_is_not_found(): void
    {
        Queue::fake();

        $payments = $this->createMock(PaymentRepositoryContract::class);
        $payments->method('findById')->willReturn(null);

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->expects($this->never())->method('existsForTransaction');

        $audit = $this->createMock(AuditEventRepositoryContract::class);
        $audit->expects($this->never())->method('append');

        $this->coordinator($payments, $receipts, $audit)->handle(new PaymentValidated(
            paymentUlid: '00000000000000000000000000',
            gatewayOrderId: 'order_missing',
            status: TransactionStatus::CAPTURED,
            occurredAt: new \DateTimeImmutable(),
        ));

        Queue::assertNotPushed(GenerateReceiptJob::class);
    }

    private function coordinator(
        PaymentRepositoryContract $payments,
        ReceiptRepositoryContract $receipts,
        AuditEventRepositoryContract $audit,
    ): ReceiptIssuanceCoordinator {
        $clock = $this->createMock(Clock::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable());

        return new ReceiptIssuanceCoordinator($payments, $receipts, $audit, $clock);
    }

    private function eventFor(Payment $payment): PaymentValidated
    {
        return new PaymentValidated(
            paymentUlid: $payment->id()->ulid(),
            gatewayOrderId: 'order_test',
            status: $payment->status(),
            occurredAt: new \DateTimeImmutable(),
        );
    }

    private function paymentInStatus(TransactionStatus $status): Payment
    {
        $payment = Payment::initialize(
            donationId: EntityId::generate('donation'),
            providerCode: PaymentProvider::RAZORPAY,
            amountMinor: 10_000,
            currency: Currency::INR,
            idempotencyKey: 'idem_'.bin2hex(random_bytes(4)),
            metadata: [],
        );

        $row = $payment->toArray();
        $row['status'] = $status->value;
        if ($status === TransactionStatus::CAPTURED) {
            $row['amount_captured_minor'] = 10_000;
        }

        return Payment::fromRow($row);
    }
}
