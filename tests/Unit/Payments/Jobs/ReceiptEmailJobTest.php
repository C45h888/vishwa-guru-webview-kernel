<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Jobs;

use App\Jobs\ReceiptEmailJob;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Repositories\ReceiptRepositoryContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Unit tests for ReceiptEmailJob.
 *
 * Doctrine:
 *   - Job is idempotent on "receipt:{id}:email" within a 7d TTL.
 *   - Skips silently when the receipt has no donor email (anonymous
 *     donation — the success page URL is the donor's own path).
 *   - Fires one Mail::raw() per call with the receipt URL signed via
 *     the receipt's access_token (A3).
 *
 * Extends the framework-booting Tests\TestCase (not the bare PHPUnit
 * one) so Mail::fake() resolves the facade. The receipt repo is a
 * plain PHPUnit mock — the framework only needs to be booted for the
 * mailer, not for the rest of the orchestration.
 *
 * These tests pin the contract; future work (HTML body, attachment,
 * retry-on-bounce) extends from here.
 */
final class ReceiptEmailJobTest extends TestCase
{
    public function testSkipsWhenReceiptNotFound(): void
    {
        Mail::fake();

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn(null);

        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $job->handle($receipts);

        Mail::assertNothingSent();
    }

    public function testSkipsWhenDonorEmailIsNull(): void
    {
        Mail::fake();

        $receipt = $this->makeReceipt(donorEmail: null);

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn($receipt);

        $job = new ReceiptEmailJob(new Identifier($receipt->id()->ulid()));
        $job->handle($receipts);

        Mail::assertNothingSent();
    }

    public function testSendsEmailWithSignedReceiptUrl(): void
    {
        Mail::fake();

        $receipt = $this->makeReceipt(
            donorEmail: 'priya@example.in',
            donorName: 'Priya Sharma',
        );
        $accessToken = $receipt->accessToken();
        $this->assertNotNull($accessToken);

        $receipts = $this->createMock(ReceiptRepositoryContract::class);
        $receipts->method('findById')->willReturn($receipt);

        $job = new ReceiptEmailJob(new Identifier($receipt->id()->ulid()));
        $job->handle($receipts);

        Mail::assertSentCount(1);
        Mail::assertSent(function ($message) use ($receipt, $accessToken): bool {
            $to = $message->getTo();
            $hasRecipient = isset($to['priya@example.in']);
            $subject = $message->getSubject() ?? '';
            $body = $message->getBody() ?? '';

            return $hasRecipient
                && str_contains($subject, $receipt->receiptNumber())
                && str_contains($body, 'Priya Sharma')
                && str_contains($body, $receipt->receiptNumber())
                && str_contains($body, 't='.urlencode($accessToken))
                // PII guard — never embed PAN/address in the email body.
                && ! str_contains($body, 'PAN')
                && ! str_contains($body, 'address');
        });
    }

    public function testIdempotencyKeyIsScopedToReceipt(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertSame(
            'receipt:01ARZ3NDEKTSV4RRFFQ69G5FAV:email',
            $job->idempotencyKey(),
        );
    }

    public function testIsFinancialJobWithSingleAttemptAndNoBackoff(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));

        // Doctrine: financial jobs fail-fast so SMTP outages surface to
        // ops instead of silently retrying.
        $this->assertSame(1, $job->tries);
        $this->assertSame([0], $job->backoff);
        $this->assertSame('receipts', $job->queue);
    }

    public function testIdempotencyTtlIsSevenDays(): void
    {
        $job = new ReceiptEmailJob(new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertSame(7 * 24 * 3600, $job->idempotencyTtl());
    }

    private function makeReceipt(
        ?string $donorEmail,
        string $donorName = 'Test Donor',
    ): Receipt {
        return Receipt::issue(
            donationId: EntityId::generate('donation'),
            paymentId: EntityId::generate('payment'),
            campaignId: EntityId::generate('campaign'),
            receiptNumber: 'TR-2026-ABC123',
            campaignTitleSnapshot: 'Temple Land Acquisition',
            donorName: $donorName,
            amountMinor: 50_000,
            currency: Currency::INR,
            contentHash: str_repeat('a', 64),
            donorEmail: $donorEmail,
        );
    }
}