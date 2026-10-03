<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Jobs;

use App\Jobs\ReceiptEmailJob;
use App\Mail\Coordinator\MailDispatchCoordinator;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Unit tests for ReceiptEmailJob — the queue carrier.
 *
 * Doctrine: the job is transport of WORK only. All delivery logic sits
 * behind the MailDispatchCoordinator seam, so these tests pin delegation
 * + the skip paths + the queue-level policy. The seam's behavior is
 * covered by MailDispatchCoordinatorTest.
 */
final class ReceiptEmailJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function testSkipsWhenReceiptNotFound(): void
    {
        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn(null);
        $this->app->instance(ReceiptRepositoryContract::class, $receipts);

        $coordinator = $this->createMock(MailDispatchCoordinator::class);
        $coordinator->expects($this->never())->method('deliverReceipt');
        $this->app->instance(MailDispatchCoordinator::class, $coordinator);

        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $job->handle();

        $this->assertTrue(true); // no exception, no send
    }

    public function testSkipsWhenDonorEmailIsNull(): void
    {
        $receipt = $this->makeReceipt(null);
        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn($receipt);
        $this->app->instance(ReceiptRepositoryContract::class, $receipts);

        $coordinator = $this->createMock(MailDispatchCoordinator::class);
        $coordinator->expects($this->never())->method('deliverReceipt');
        $this->app->instance(MailDispatchCoordinator::class, $coordinator);

        $job = new ReceiptEmailJob(new Identifier($receipt->id()->ulid()));
        $job->handle();

        $this->assertTrue(true);
    }

    public function testDelegatesDeliveryToTheSeamWithTheSignedUrl(): void
    {
        $receipt = $this->makeReceipt('priya@example.in');
        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn($receipt);
        $this->app->instance(ReceiptRepositoryContract::class, $receipts);

        $coordinator = $this->createMock(MailDispatchCoordinator::class);
        $coordinator->expects($this->once())
            ->method('deliverReceipt')
            ->with(
                $receipt,
                $this->logicalAnd(
                    $this->stringContains($receipt->receiptNumber()),
                    $this->stringContains('t='),
                ),
            )
            ->willReturn(Result::success([
                'status' => 'sent',
                'from' => 'receipts@vsrsms.in',
                'recipient' => 'priya@example.in',
            ]));
        $this->app->instance(MailDispatchCoordinator::class, $coordinator);

        $job = new ReceiptEmailJob(new Identifier($receipt->id()->ulid()));
        $job->handle();

        $this->assertTrue(true);
    }

    public function testSurfacesSeamFailuresToTheQueue(): void
    {
        $receipt = $this->makeReceipt('priya@example.in');
        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn($receipt);
        $this->app->instance(ReceiptRepositoryContract::class, $receipts);

        $coordinator = $this->createMock(MailDispatchCoordinator::class);
        $coordinator->method('deliverReceipt')
            ->willReturn(Result::failure('mail.dispatch: mail.api_error'));
        $this->app->instance(MailDispatchCoordinator::class, $coordinator);

        $this->expectException(\RuntimeException::class);

        $job = new ReceiptEmailJob(new Identifier($receipt->id()->ulid()));
        $job->handle();
    }

    public function testIdempotencyKeyIsScopedToReceipt(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertSame(
            'receipt:01ARZ3NDEKTSV4RRFFQ69G5FAV:email',
            $job->idempotencyKey(),
        );
    }

    public function testEmailJobRetriesWithBackoff(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));

        // Doctrine (2026-10-03 fix): delivery is idempotent via the
        // persisted delivery_status guard, so transient Hostinger blips
        // retry instead of leaving the receipt permanently unmailed.
        // receipts:reconcile is the final backfill safety net.
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60, 300], $job->backoff);
        $this->assertSame('receipts', $job->queue);
    }

    public function testIdempotencyTtlIsSevenDays(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertSame(7 * 24 * 3600, $job->idempotencyTtl());
    }

    private function makeReceipt(?string $donorEmail): Receipt
    {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: EntityId::generate('payment'),
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-000123-A7c3ZpQ9',
            campaignTitleSnapshot: 'Temple Land Acquisition',
            donorName: 'Priya Sharma',
            amountMinor: 50_000,
            currency: Currency::INR,
            contentHash: str_repeat('a', 64),
            donorEmail: $donorEmail,
        );
    }
}
