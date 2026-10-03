<?php

declare(strict_types=1);

namespace Tests\Feature\Payments\Console;

use App\Jobs\ReceiptEmailJob;
use App\Payments\Console\Commands\ReconcileReceiptsCommand;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\StateMachines\ReceiptStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Infrastructure\Repositories\ReceiptRepository;
use App\Persistence\ValueObjects\EntityId;
use DateTimeImmutable;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * receipts:reconcile — email backfill pass (2026-10-03).
 *
 * Covers the second pass: ReceiptEmailJob re-dispatch for receipts whose
 * email delivery never durably completed (failed status, stale pending).
 * The queue carrier is captured by Queue::fake and never runs.
 *
 * @covers ReconcileReceiptsCommand
 */
final class ReconcileReceiptsCommandTest extends InfrastructureTestCase
{
    private ReceiptRepository $receipts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receipts = new ReceiptRepository($this->adapter);
    }

    public function testDispatchesEmailJobsForUncompletedDeliveries(): void
    {
        $failed = $this->seedFailedReceipt('failed@reconcile.test');
        $stalePending = $this->seedStalePendingReceipt('stale@reconcile.test');

        Queue::fake();

        $this->artisan(ReconcileReceiptsCommand::class)
            ->assertSuccessful();

        Queue::assertPushed(ReceiptEmailJob::class, 2);
        Queue::assertPushed(ReceiptEmailJob::class, function (ReceiptEmailJob $job) use ($failed) {
            return $job->receiptId->value() === $failed->id()->ulid();
        });
        Queue::assertPushed(ReceiptEmailJob::class, function (ReceiptEmailJob $job) use ($stalePending) {
            return $job->receiptId->value() === $stalePending->id()->ulid();
        });
    }

    public function testSkipsDeliveredFreshPendingAndAnonymousReceipts(): void
    {
        $delivered = $this->seedReceipt('delivered@reconcile.test');
        $this->receipts->update($delivered->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_DISPATCHED,
        ));

        $this->seedReceipt('fresh@reconcile.test'); // pending, inside grace window
        $this->seedReceipt(null); // no donor email

        Queue::fake();

        $this->artisan(ReconcileReceiptsCommand::class)
            ->assertSuccessful();

        Queue::assertNotPushed(ReceiptEmailJob::class);
    }

    public function testNothingToBackfillReportsClean(): void
    {
        Queue::fake();

        $this->artisan(ReconcileReceiptsCommand::class)
            ->expectsOutput('receipts:reconcile — nothing to backfill.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    // ------------------------------------------------------------------

    private function seedReceipt(?string $donorEmail): Receipt
    {
        $receipt = Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: EntityId::generate('payment'),
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000002-'.strtoupper(bin2hex(random_bytes(4))),
            campaignTitleSnapshot: 'Reconcile Campaign',
            donorName: 'Reconcile Donor',
            amountMinor: 15000,
            currency: Currency::INR,
            contentHash: hash('sha256', $donorEmail ?? 'anon'.random_int(1, 999999)),
            donorEmail: $donorEmail,
        );
        $this->receipts->save($receipt);

        return $receipt;
    }

    private function seedFailedReceipt(string $donorEmail): Receipt
    {
        $receipt = $this->seedReceipt($donorEmail);
        $this->receipts->update($receipt->transitionDelivery(
            new ReceiptStateMachine(),
            StateTransitionEvent::DELIVERY_FAILED,
            ['channel' => 'email', 'address' => $donorEmail],
        ));

        return $receipt;
    }

    private function seedStalePendingReceipt(string $donorEmail): Receipt
    {
        $receipt = $this->seedReceipt($donorEmail);

        // Backdate beyond the 1h pending grace window.
        $this->adapter->execute(
            'UPDATE receipts SET updated_at = :ts WHERE id = :id',
            ['ts' => (new DateTimeImmutable('-2 hours'))->format(DATE_ATOM), 'id' => $receipt->id()->value()],
        );

        return $receipt;
    }
}